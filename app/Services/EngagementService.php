<?php

namespace App\Services;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Models\Liquidation;
use App\Services\Budget\ExerciceService;
use App\Support\AuditLogger;
use App\Support\Numero;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EngagementService
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly ExerciceService $exercices,
    ) {
    }

    /**
     * Crée un engagement (brouillon) avec ses imputations multiples.
     * @param array<int,array{ligne_budgetaire_id:int,montant:float}> $imputations
     */
    public function creer(array $data, array $imputations, User $user): Engagement
    {
        $imputations = array_values(array_filter($imputations, fn ($i) => (float) ($i['montant'] ?? 0) > 0));
        if ($imputations === []) {
            throw new RuntimeException('Au moins une imputation budgétaire est requise.');
        }

        $this->exercices->assertExerciceOuvert((int) $data['exercice_id']);
        $this->assertBesoinRequis($data, $user);

        $besoin = isset($data['expression_besoin_id'])
            ? ExpressionBesoin::find($data['expression_besoin_id'])
            : null;

        if ($besoin && ! isset($data['activite_id'])) {
            $data['activite_id'] = $besoin->activite_id;
        }

        if ($besoin && ! isset($data['tache_id']) && $besoin->tache_id) {
            $data['tache_id'] = $besoin->tache_id;
        }

        $annee = Exercice::find($data['exercice_id'])?->annee ?? (int) date('Y');
        $total = array_sum(array_map(fn ($i) => (float) $i['montant'], $imputations));

        return DB::transaction(function () use ($data, $imputations, $user, $annee, $total) {
            $engagement = Engagement::create([
                ...$data,
                'numero' => Numero::suivant('engagements', 'ENG', $annee),
                'montant_total' => $total,
                'statut' => 'brouillon',
                'cree_par' => $user->id,
                'credits_reserves' => false,
            ]);

            foreach ($imputations as $imp) {
                $engagement->imputations()->create([
                    'ligne_budgetaire_id' => $imp['ligne_budgetaire_id'],
                    'montant' => (float) $imp['montant'],
                ]);
            }

            return $engagement;
        });
    }

    /** @param array<string,mixed> $data */
    private function assertBesoinRequis(array $data, User $user): void
    {
        if ($user->can('depenses.admin')) {
            return;
        }

        $besoinId = $data['expression_besoin_id'] ?? null;
        if (! $besoinId) {
            throw new RuntimeException('Un besoin validé est requis pour créer un engagement.');
        }

        $besoin = ExpressionBesoin::find($besoinId);
        if (! $besoin || ! in_array($besoin->statut, ['valide', 'converti'], true)) {
            throw new RuntimeException('Le besoin doit être validé avant engagement.');
        }

        if (! $besoin->activite_id) {
            throw new RuntimeException('Le besoin doit être rattaché à une activité PAP.');
        }

        if (! $besoin->ligne_budgetaire_id) {
            throw new RuntimeException('Le besoin doit être rattaché à une ligne budgétaire.');
        }
    }

    /**
     * Soumet l'engagement : réserve les crédits (contrôle de disponibilité) puis
     * démarre le circuit de visa du contrôleur financier.
     */
    public function soumettre(Engagement $engagement, User $user): void
    {
        if (! $engagement->estModifiable()) {
            throw new RuntimeException('Cet engagement ne peut plus être soumis.');
        }

        $this->exercices->assertExerciceOuvert($engagement->exercice_id);

        // Séparation des fonctions : le créateur ne vise pas ; le contrôle est porté par le rôle de l'étape.
        $engagement->reserverCredits();                 // contrôle + réservation atomique (tout ou rien)
        app(\App\Services\Depense\CertificatEngagementService::class)
            ->enregistrer($engagement, 'reservation', $user);
        $this->workflow->demarrer($engagement, 'ENGAGEMENT', $user);

        app(\App\Services\Notifications\DepenseNotificationService::class)
            ->notifierEngagementSoumis($engagement->fresh());

        AuditLogger::enregistrer('engagement_soumis', $engagement, null, ['montant' => (float) $engagement->montant_total]);
    }

    /** Annule un engagement et libère les crédits éventuellement réservés. */
    public function annuler(Engagement $engagement, User $user): void
    {
        if ($engagement->liquidations()->whereNotIn('statut', ['rejete'])->exists()) {
            throw new RuntimeException('Annulation impossible : des liquidations existent sur cet engagement.');
        }

        $engagement->libererCredits();
        $engagement->update(['statut' => 'annule']);
        AuditLogger::enregistrer('engagement_annule', $engagement);
    }
}
