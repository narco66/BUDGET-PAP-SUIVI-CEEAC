<?php

namespace App\Console\Commands;

use App\Models\WorkflowTache;
use App\Services\Workflow\TachePreparationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Régularisation « Mes Tâches » : crée les tâches de préparation des dossiers de dépense existants dont
 * une action humaine est attendue hors circuit (EB à soumettre ou corriger, ENG / LIQ / ORD / PAY à
 * soumettre, corriger ou préparer, solde à payer) et clôt celles devenues sans objet.
 * Idempotente ; ne modifie aucun dossier, aucune instance ni aucune étape de workflow.
 */
class TachesSynchroniserPreparationCommand extends Command
{
    protected $signature = 'taches:synchroniser-preparation {--dry-run : Lister les actions attendues sans rien créer}';

    protected $description = 'Crée ou clôt les tâches de préparation de la chaîne de dépense selon l\'état réel des dossiers.';

    public function handle(TachePreparationService $preparation): int
    {
        $simulation = (bool) $this->option('dry-run');
        $lignes = [];
        $creees = $closes = 0;

        foreach (TachePreparationService::MODELES as $classe) {
            $classe::query()->orderBy('id')->each(function ($objet) use ($preparation, $simulation, $classe, &$lignes, &$creees, &$closes) {
                $attendue = $preparation->actionAttendue($objet);
                $ouverte = WorkflowTache::query()->where('nature', WorkflowTache::NATURE_PREPARATION)
                    ->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())->ouvertes()->value('action_code');
                if ($attendue === null && $ouverte === null) {
                    return;
                }
                if ($simulation) {
                    if (($attendue['code'] ?? null) !== $ouverte) {
                        $lignes[] = [class_basename($classe), $objet->numero ?? $objet->getKey(), $objet->statut, $ouverte ?? '—', $attendue['code'] ?? '— (à clore)', $attendue['role'] ?? '—'];
                    }

                    return;
                }
                $r = $preparation->synchroniser($classe, $objet->getKey());
                if ($r['creee']) {
                    $creees++;
                    $lignes[] = [class_basename($classe), $objet->numero ?? $objet->getKey(), $objet->statut, $ouverte ?? '—', $r['creee']->reference.' ('.$r['creee']->action_code.')', $r['creee']->role];
                }
                $closes += $r['closes'];
            });
        }

        if ($lignes) {
            $this->table(['Dossier', 'Numéro', 'Statut', 'Tâche ouverte', $simulation ? 'Action attendue' : 'Tâche créée', 'Rôle'], $lignes);
        }
        $this->info($simulation
            ? count($lignes).' écart(s) à régulariser (simulation : aucune écriture).'
            : "Tâches de préparation créées : {$creees} ; closes : {$closes}.");

        return self::SUCCESS;
    }
}
