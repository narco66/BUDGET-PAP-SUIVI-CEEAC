<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Services\EngagementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BesoinVersEngagementService
{
    public function __construct(private readonly EngagementService $engagements) {}

    public function assurerDepuisBesoinApprouve(ExpressionBesoin $besoin, ?User $acteur = null): Engagement
    {
        return DB::transaction(function () use ($besoin, $acteur) {
            /** @var ExpressionBesoin $verrouille */
            $verrouille = ExpressionBesoin::query()->whereKey($besoin->id)->lockForUpdate()->firstOrFail();

            $existant = Engagement::query()
                ->where('expression_besoin_id', $verrouille->id)
                ->lockForUpdate()
                ->first();
            if ($existant) {
                return $existant;
            }

            if (! in_array($verrouille->statut, ['valide', 'converti'], true)) {
                throw new RuntimeException('Le besoin doit être approuvé avant génération de l’engagement.');
            }

            if (! $verrouille->ligne_budgetaire_id) {
                throw new RuntimeException('Imputation budgétaire manquante : engagement refusé.');
            }

            $user = $acteur ?? $verrouille->demandeur;
            if (! $user) {
                throw new RuntimeException('Aucun acteur pour créer l’engagement.');
            }

            $engagement = $this->engagements->creerDepuisBesoinApprouve($verrouille, $user);
            $this->soumettreAutomatiquement($engagement, $verrouille->demandeur ?? $user);

            return $engagement->fresh();
        });
    }

    /**
     * Procédure ENG § 3.4 : si les crédits sont suffisants, réservation puis soumission à la Direction du Budget
     * (circuit, tâche et notification de l'étape). Sinon l'engagement reste en attente (insuffisance enregistrée)
     * et le demandeur est averti ; l'approbation de l'EB n'est jamais annulée.
     */
    private function soumettreAutomatiquement(Engagement $engagement, User $initiateur): void
    {
        if (! config('chaine_depense.automatisation.soumission_engagement', true)) {
            return;
        }
        try {
            $this->engagements->soumettre($engagement, $initiateur);
        } catch (RuntimeException $e) {
            app(\App\Services\Notifications\DepenseNotificationService::class)
                ->notifierEngagementNonSoumis($engagement->fresh(), $e->getMessage());
            \App\Support\AuditLogger::evenement('engagement_soumission_auto_echec', $engagement, [
                'motif' => $e->getMessage(), 'gravite' => 'modere', 'resultat' => 'echec',
            ]);
        }
    }
}
