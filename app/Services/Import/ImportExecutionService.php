<?php

namespace App\Services\Import;

use App\Jobs\ExecuterImportJob;
use App\Models\ImportLigne;
use App\Models\ImportLot;
use App\Models\User;
use App\Services\Import\Support\ContexteImport;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationCriticite;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Exécution d'un lot : verrou (une seule exécution), contrôle de dérive (la base a-t-elle changé depuis la
 * simulation ?), écriture transactionnelle de tout le lot (parents avant enfants), vérification après import,
 * journal et notification. En cas d'échec, rien n'est écrit (annulation de la transaction).
 */
class ImportExecutionService
{
    public function __construct(
        private readonly ImportDomaineRegistry $domaines,
        private readonly ImportAnalyseService $analyses,
        private readonly ImportLotService $lots,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function peutExecuter(ImportLot $lot): bool
    {
        $sensible = $this->domaines->get($lot->domaine)->circuit() !== null;

        return $lot->mode !== 'simulation'
            && $lot->total('a_ecrire') > 0
            && ($sensible ? $lot->statut === 'approuve' : $lot->statut === 'simule');
    }

    /** Lance l'exécution : immédiate, ou par un job en file au-delà du seuil (suivi d'avancement). */
    public function lancer(ImportLot $lot, User $user): ImportLot
    {
        if (! $this->peutExecuter($lot)) {
            throw new RuntimeException($this->domaines->get($lot->domaine)->circuit() && in_array($lot->statut, ['simule', 'en_attente_approbation'], true)
                ? 'Lot sensible : l\'approbation est requise avant l\'exécution.'
                : 'Ce lot n\'est pas exécutable dans son état actuel (« '.$lot->libelleStatut().' »).');
        }
        if (! $this->domaines->get($lot->domaine)->autorise($user)) {
            throw new RuntimeException('Vous n\'avez pas le droit métier d\'écrire dans ce domaine.');
        }
        if ($lot->total('a_ecrire') > config('import.seuil_asynchrone')) {
            $this->reserver($lot, $user);
            ExecuterImportJob::dispatch($lot->id, $user->id);

            return $lot->fresh();
        }

        return $this->executer($lot, $user);
    }

    public function executer(ImportLot $lot, User $user): ImportLot
    {
        if ($lot->statut !== 'en_cours') {
            $this->reserver($lot, $user);
        }
        $lot = $lot->fresh();
        $domaine = $this->domaines->get($lot->domaine);

        // Contrôle de dérive : le plan simulé doit être toujours valable au moment d'écrire.
        $derives = $this->derives($lot);
        if ($derives > 0) {
            $lot->update(['statut' => 'simule', 'approuve_par' => null, 'approuve_le' => null]);
            $lot = $this->lots->analyser($lot->fresh());
            $this->lots->journaliser($lot, 'derive', 'en_cours', $lot->statut, "{$derives} ligne(s) ont changé depuis l'analyse : plan recalculé, nouvelle validation requise.");
            throw new RuntimeException("La base a changé depuis l'analyse ({$derives} ligne(s)) : le plan a été recalculé. Vérifiez la prévisualisation puis soumettez de nouveau le lot.");
        }

        $ctx = new ContexteImport($lot, $lot->exercice, $lot->mode, $user);
        $lignes = $lot->lignes()->whereIn('operation', ImportLigne::ECRITURES)->get()
            ->sortBy([fn ($a, $b) => $domaine->rangExecution($a) <=> $domaine->rangExecution($b), ['numero', 'asc']])->values();

        try {
            DB::transaction(function () use ($lignes, $domaine, $ctx) {
                $domaine->preparer($ctx);
                foreach ($lignes as $ligne) {
                    $modele = $domaine->executer($ligne, $ctx);
                    $ligne->update(['statut' => 'execute', 'cible_type' => $modele?->getMorphClass(), 'cible_id' => $modele?->getKey()]);
                }
            });
        } catch (\Throwable $e) {
            $lot->lignes()->where('statut', 'execute')->update(['statut' => 'en_attente']);
            $message = mb_substr($e->getMessage() ?: class_basename($e), 0, 1000);
            $lot->update(['statut' => 'echec', 'motif' => $message, 'termine_le' => now(), 'progression' => 0]);
            $this->lots->journaliser($lot, 'echec', 'en_cours', 'echec', $message);
            $this->notifier($lot, false);
            report($e);

            return $lot->fresh();
        }

        // Vérification après import : chaque écriture prévue a bien été réalisée.
        $executees = $lot->lignes()->where('statut', 'execute')->count();
        $totaux = ($lot->totaux ?? []) + [];
        $totaux['executees'] = $executees;
        $lot->update(['statut' => 'termine', 'totaux' => $totaux, 'termine_le' => now(), 'progression' => 100, 'motif' => null]);
        $this->lots->journaliser($lot, 'execution', 'en_cours', 'termine', "{$executees} ligne(s) intégrée(s).", [
            'executees' => $executees, 'prevues' => $lot->total('a_ecrire'), 'empreinte' => $lot->fichier_empreinte,
        ]);
        $this->notifier($lot->fresh(), true);

        return $lot->fresh();
    }

    /** Réserve le lot (verrou) : deux exécutions simultanées ne peuvent pas aboutir. */
    private function reserver(ImportLot $lot, User $user): void
    {
        DB::transaction(function () use ($lot, $user) {
            $verrou = ImportLot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
            if (! $this->peutExecuter($verrou)) {
                throw new RuntimeException('Ce lot est déjà en cours d\'exécution ou n\'est plus exécutable.');
            }
            $avant = $verrou->statut;
            $verrou->update(['statut' => 'en_cours', 'execute_par' => $user->id, 'execute_le' => now(), 'progression' => 0]);
            $this->lots->journaliser($verrou, 'debut_execution', $avant, 'en_cours');
        });
    }

    /** Nombre de lignes dont l'opération ou la cible a changé entre la simulation et maintenant. */
    private function derives(ImportLot $lot): int
    {
        $stockees = $lot->lignes()->get(['numero', 'operation', 'cible_id'])->keyBy('numero');
        $derives = 0;
        foreach ($this->analyses->calculer($lot) as ['numero' => $numero, 'analyse' => $a]) {
            $s = $stockees[$numero] ?? null;
            if (! $s || $s->operation !== $a->operation || (int) $s->cible_id !== (int) $a->cible?->getKey()) {
                $derives++;
            }
        }

        return $derives;
    }

    private function notifier(ImportLot $lot, bool $succes): void
    {
        $destinataires = User::query()->whereIn('id', array_filter([$lot->cree_par, $lot->execute_par]))->get();
        try {
            DB::transaction(fn () => $this->dispatcher->envoyer(
                $destinataires,
                $succes ? 'import.termine' : 'import.echec',
                $succes ? "Import {$lot->reference} terminé" : "Échec de l'import {$lot->reference}",
                $succes
                    ? $lot->total('executees').' ligne(s) intégrée(s) dans « '.$this->domaines->get($lot->domaine)->libelle().' ».'
                    : 'Aucune donnée n\'a été intégrée : '.($lot->motif ?? 'erreur technique').'.',
                route('imports.show', $lot),
                'import',
                $succes ? NotificationCriticite::INFORMATION : NotificationCriticite::AVERTISSEMENT,
                ressource: $lot,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
