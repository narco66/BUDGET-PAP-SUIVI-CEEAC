<?php

namespace App\Services\Alertes;

use App\Models\Alerte;
use App\Models\AlertePerformance;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationCriticite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Registre transversal des alertes institutionnelles (N-P1). */
class AlerteRegistryService
{
    public function __construct(
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function enregistrer(
        string $module,
        string $code,
        string $titre,
        string $message,
        string $criticite = NotificationCriticite::ATTENTION,
        ?int $exerciceId = null,
        ?Model $objet = null,
        ?int $assigneA = null,
        array $metadata = [],
        bool $notifier = true,
    ): Alerte {
        $query = Alerte::query()
            ->where('module', $module)
            ->where('code', $code)
            ->where('statut', 'ouverte');

        if ($objet) {
            $query->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey());
        } else {
            $query->whereNull('objet_type')->whereNull('objet_id');
            if ($exerciceId) {
                $query->where('exercice_id', $exerciceId);
            }
        }

        $existante = $query->first();

        if ($existante) {
            $existante->update([
                'titre' => $titre,
                'message' => $message,
                'criticite' => $criticite,
                'structure_id' => $this->resoudreStructureId($objet, $assigneA),
                'metadata' => array_merge($existante->metadata ?? [], $metadata),
                'detecte_le' => now(),
            ]);

            return $existante->fresh();
        }

        $alerte = Alerte::create([
            'reference' => $this->genererReference($module, $code),
            'module' => $module,
            'code' => $code,
            'criticite' => $criticite,
            'titre' => $titre,
            'message' => $message,
            'exercice_id' => $exerciceId,
            'structure_id' => $this->resoudreStructureId($objet, $assigneA),
            'objet_type' => $objet?->getMorphClass(),
            'objet_id' => $objet?->getKey(),
            'statut' => 'ouverte',
            'assigne_a' => $assigneA,
            'metadata' => $metadata ?: null,
            'detecte_le' => now(),
        ]);

        if ($notifier) {
            $this->notifierNouvelleAlerte($alerte);
        }

        return $alerte;
    }

    public function resoudre(Alerte $alerte, User $user, ?string $commentaire = null): Alerte
    {
        $alerte->update([
            'statut' => 'resolue',
            'resolu_par' => $user->id,
            'resolu_le' => now(),
            'resolu_commentaire' => $commentaire,
        ]);

        return $alerte->fresh();
    }

    public function synchroniserDepuisPerformance(AlertePerformance $ap): Alerte
    {
        $criticite = match ($ap->gravite) {
            'elevee' => NotificationCriticite::CRITIQUE,
            'faible' => NotificationCriticite::ATTENTION,
            default => NotificationCriticite::AVERTISSEMENT,
        };

        return $this->enregistrer(
            'suivi',
            'performance.'.$ap->type,
            'Alerte performance — '.$ap->reference,
            $ap->message,
            $criticite,
            $ap->exercice_id,
            $ap->activite,
            metadata: ['alerte_performance_id' => $ap->id, 'campagne_id' => $ap->campagne_id],
        );
    }

    /** @return Collection<int, User> */
    public function destinatairesAlerte(Alerte $alerte): Collection
    {
        if ($alerte->assigne_a) {
            $user = User::find($alerte->assigne_a);
            if ($user?->actif) {
                return collect([$user]);
            }
        }

        $roles = match ($alerte->module) {
            'budget', 'recettes' => ['controleur_financier', 'commissaire'],
            'pap', 'suivi' => ['directeur', 'chef_service', 'commissaire'],
            'depense' => ['controleur_financier', 'gestionnaire_budgetaire'],
            default => ['commissaire', 'directeur'],
        };

        return User::query()
            ->where('actif', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->get();
    }

    private function notifierNouvelleAlerte(Alerte $alerte): void
    {
        $destinataires = $this->destinatairesAlerte($alerte);
        if ($destinataires->isEmpty()) {
            return;
        }

        $url = route('alertes.index', ['module' => $alerte->module, 'statut' => 'ouverte']);

        $this->notifications->envoyer(
            $destinataires,
            'alerte.institutionnelle',
            $alerte->titre,
            $alerte->message,
            $url,
            $alerte->module,
            $alerte->criticite,
            meta: ['alerte_id' => $alerte->id, 'alerte_code' => $alerte->code],
            canaux: ['database', 'email'],
        );

        if (in_array($alerte->criticite, [NotificationCriticite::CRITIQUE, NotificationCriticite::URGENCE], true)) {
            app(AlerteEscaladeService::class)->escalader($alerte, 1);
        }
    }

    private function genererReference(string $module, string $code): string
    {
        do {
            $ref = strtoupper($module).'-'.Str::upper(Str::slug($code, '-')).'-'.now()->format('YmdHis').'-'.Str::random(4);
        } while (Alerte::where('reference', $ref)->exists());

        return $ref;
    }

    private function resoudreStructureId(?Model $objet, ?int $assigneA = null): ?int
    {
        if ($objet) {
            if (method_exists($objet, 'structureIdWorkflow')) {
                return $objet->structureIdWorkflow();
            }

            if (isset($objet->structure_id)) {
                return $objet->structure_id ? (int) $objet->structure_id : null;
            }

            if (method_exists($objet, 'structure')) {
                return $objet->structure?->id;
            }
        }

        return $assigneA ? User::whereKey($assigneA)->value('structure_id') : null;
    }
}
