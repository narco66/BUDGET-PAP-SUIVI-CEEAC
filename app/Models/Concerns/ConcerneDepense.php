<?php

namespace App\Models\Concerns;

use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use App\Services\Depense\DepenseCompteurService;
use App\Services\Depense\DepenseQuotaService;
use App\Services\Pap\PapPerformanceService;
use Illuminate\Support\Facades\DB;

/**
 * Comportement commun aux étapes aval de la dépense (Liquidation, Ordonnancement, Paiement).
 * À la validation du circuit, applique le compteur correspondant sur les lignes budgétaires
 * imputées par l'engagement racine (idempotent via compteurs_appliques).
 *
 * Le modèle doit fournir : colonneCompteur(): string et engagementRacine(): Engagement.
 */
trait ConcerneDepense
{
    abstract public function colonneCompteur(): string;

    abstract public function engagementRacine(): Engagement;

    public function structureIdWorkflow(): ?int
    {
        $engagement = $this->relationLoaded('engagement')
            ? $this->engagementRacine()
            : $this->engagementRacine()->loadMissing('expressionBesoin');

        return $engagement->structureIdWorkflow();
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'rejete'], true);
    }

    public function auWorkflowDemarre(): void
    {
        $this->update(['statut' => 'soumis']);
    }

    public function auEtapeValidee(int $ordre): void
    {
    }

    public function auWorkflowValide(): void
    {
        if ($this instanceof \App\Models\Liquidation) {
            app(DepenseQuotaService::class)->assertLiquidationCoherente($this);
        }

        $this->appliquerCompteur();
        $this->update(['statut' => 'valide', 'valide_le' => now()]);

        app(\App\Services\Depense\HistoriqueDepenseService::class)->enregistrer($this, 'etape_validee');

        if ($this instanceof \App\Models\Liquidation) {
            app(\App\Services\Depense\LiquidationVersOrdonnancementService::class)
                ->assurerDepuisLiquidationValidee($this->fresh());
        }
    }

    public function auWorkflowRejete(): void
    {
        $this->update(['statut' => 'rejete']);
        app(\App\Services\Depense\HistoriqueDepenseService::class)->enregistrer($this, 'etape_rejetee');
    }

    protected function appliquerCompteur(): void
    {
        if ($this->compteurs_appliques) {
            return;
        }

        $colonne = $this->colonneCompteur();
        $montantEtape = method_exists($this, 'montantPourCompteurs')
            ? (float) $this->montantPourCompteurs()
            : (float) $this->montant;
        $imputations = $this->engagementRacine()->imputations()->get();
        $totalEngagement = (float) $imputations->sum('montant');

        if ($totalEngagement <= 0 || $montantEtape <= 0) {
            return;
        }

        DB::transaction(function () use ($colonne, $montantEtape, $imputations, $totalEngagement) {
            $parts = app(DepenseCompteurService::class)->repartir($montantEtape, $imputations);
            $ligneIds = array_keys($parts);

            foreach ($parts as $ligneId => $part) {
                LigneBudgetaire::whereKey($ligneId)->increment($colonne, $part);
            }

            $this->update(['compteurs_appliques' => true]);

            if ($colonne === 'montant_paye') {
                app(PapPerformanceService::class)->synchroniserDepuisLignes($ligneIds);
            }
        });
    }
}
