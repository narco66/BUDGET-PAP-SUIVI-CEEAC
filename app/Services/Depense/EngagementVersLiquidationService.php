<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Models\Liquidation;
use App\Models\User;
use App\Services\Notifications\DepenseNotificationService;
use App\Services\WorkflowService;
use App\Support\AuditLogger;
use App\Support\Numero;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EngagementVersLiquidationService
{
    public function assurerDepuisEngagementVise(Engagement $engagement): Liquidation
    {
        return DB::transaction(function () use ($engagement) {
            $verrouille = Engagement::query()->whereKey($engagement->id)->lockForUpdate()->firstOrFail();

            $existante = Liquidation::query()
                ->where('engagement_id', $verrouille->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if ($existante) {
                return $existante;
            }

            $annee = $verrouille->exercice?->annee ?? (int) date('Y');

            $liquidation = Liquidation::create([
                'numero' => Numero::suivant('liquidations', 'LIQ', $annee),
                'engagement_id' => $verrouille->id,
                'montant' => $verrouille->montant_total,
                'numero_tranche' => 1,
                'statut' => 'brouillon',
                'cree_par' => $verrouille->cree_par,
            ]);
            $this->demarrerCircuit($liquidation, $verrouille);

            return $liquidation->fresh();
        });
    }

    /**
     * Procédure LIQ § 3.1-4 : la liquidation créée au visa entre dans son circuit ; la première étape
     * (certification du service fait) devient la tâche du service initiateur, qui en est notifié.
     */
    private function demarrerCircuit(Liquidation $liquidation, Engagement $engagement): void
    {
        if (! config('chaine_depense.automatisation.demarrage_liquidation', true)) {
            return;
        }
        $initiateur = User::find($engagement->cree_par) ?? $engagement->expressionBesoin?->demandeur;
        try {
            if (! $initiateur) {
                throw new RuntimeException('Aucun initiateur pour lancer le circuit de liquidation.');
            }
            app(WorkflowService::class)->demarrer($liquidation, 'LIQUIDATION', $initiateur);
        } catch (RuntimeException $e) {
            AuditLogger::evenement('liquidation_demarrage_auto_echec', $liquidation, [
                'motif' => $e->getMessage(), 'gravite' => 'modere', 'resultat' => 'echec',
            ]);
        }
        app(DepenseNotificationService::class)->notifierLiquidationGeneree($liquidation->fresh());
    }
}
