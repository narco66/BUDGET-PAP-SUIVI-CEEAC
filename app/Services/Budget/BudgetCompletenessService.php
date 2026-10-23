<?php

namespace App\Services\Budget;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;

/** Complétion idempotente des données budget (phase B0 : rapprochements, ventilations PTF, liaisons RBM). */
class BudgetCompletenessService
{
    public function __construct(
        private readonly PapBudgetRapprochementService $rapprochement,
        private readonly RbmBudgetSyncService $sync,
        private readonly LigneFinancementService $financement,
        private readonly BudgetAuditService $audit,
    ) {
    }

    /**
     * @param  array{rapprocher?:bool,reconcilier?:bool,ventilations?:bool,lignes_structurelles?:bool,dry_run?:bool}  $options
     * @return array<string,mixed>
     */
    public function completer(int $exerciceId, array $options = []): array
    {
        $exercice = Exercice::findOrFail($exerciceId);
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $stats = [
            'exercice_id' => $exerciceId,
            'annee' => $exercice->annee,
            'rapprochement' => [],
            'activites_sync' => 0,
            'lignes_sync' => 0,
            'besoins_rattaches' => 0,
            'ventilations_corrigees' => 0,
            'lignes_structurelles_corrigees' => 0,
        ];

        if ($options['rapprocher'] ?? true) {
            $stats['rapprochement'] = $this->rapprochement->rapprocher($exerciceId, [
                'lier_codes' => true,
                'aligner_montants' => true,
                'dry_run' => $dryRun,
            ]);
        }

        if ($options['reconcilier'] ?? true) {
            $stats = array_merge($stats, $this->reconcilier($exerciceId, $dryRun));
        }

        if ($options['ventilations'] ?? true) {
            $stats['ventilations_corrigees'] = $this->resynchroniserVentilations($exerciceId, $dryRun);
        }

        if ($options['lignes_structurelles'] ?? true) {
            $stats['lignes_structurelles_corrigees'] = $this->corrigerLignesStructurelles($exerciceId, $dryRun);
        }

        if (! $dryRun) {
            AuditLogger::enregistrer('budget_completer_b0', $exercice, null, $stats);
            app(\App\Services\Pap\PapPerformanceService::class)->synchroniserTauxFinanciersExercice($exerciceId);
        }

        $rapport = $this->audit->executer($exerciceId);
        $stats['anomalies_total'] = $rapport['anomalies_total'];
        $stats['rbm_budget'] = $rapport['rbm_budget'];
        $stats['referentiel_financier'] = [
            'total_anomalies' => $rapport['referentiel_financier']['total_anomalies'] ?? 0,
            'lignes_ouvertes_sans_activite' => $rapport['rbm_budget']['lignes_ouvertes_sans_activite'] ?? 0,
        ];
        $stats['succes'] = $rapport['anomalies_total'] === 0;

        return $stats;
    }

    /** @return array{activites_sync:int,lignes_sync:int,besoins_rattaches:int} */
    private function reconcilier(int $exerciceId, bool $dryRun): array
    {
        $stats = ['activites_sync' => 0, 'lignes_sync' => 0, 'besoins_rattaches' => 0];

        $activites = Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('ligne_budgetaire_id')
            ->get();

        foreach ($activites as $activite) {
            if ($dryRun) {
                $ligne = LigneBudgetaire::find($activite->ligne_budgetaire_id);
                if ($ligne && (int) $ligne->activite_id !== (int) $activite->id) {
                    $stats['activites_sync']++;
                }
            } else {
                $this->sync->synchroniserActivite($activite);
                $stats['activites_sync']++;
            }
        }

        $lignes = LigneBudgetaire::query()
            ->where('exercice_id', $exerciceId)
            ->whereNotNull('activite_id')
            ->get();

        foreach ($lignes as $ligne) {
            if ($dryRun) {
                $activite = Activite::find($ligne->activite_id);
                if ($activite && (int) $activite->ligne_budgetaire_id !== (int) $ligne->id) {
                    $stats['lignes_sync']++;
                }
            } else {
                $this->sync->synchroniserLigne($ligne);
                $stats['lignes_sync']++;
            }
        }

        ExpressionBesoin::query()
            ->where('exercice_id', $exerciceId)
            ->whereNotNull('activite_id')
            ->whereNull('ligne_budgetaire_id')
            ->each(function (ExpressionBesoin $besoin) use ($dryRun, &$stats) {
                $ligneId = Activite::find($besoin->activite_id)?->ligne_budgetaire_id;
                if (! $ligneId) {
                    return;
                }

                if ($dryRun) {
                    $stats['besoins_rattaches']++;
                } else {
                    $besoin->update(['ligne_budgetaire_id' => $ligneId]);
                    $stats['besoins_rattaches']++;
                }
            });

        return $stats;
    }

    private function resynchroniserVentilations(int $exerciceId, bool $dryRun): int
    {
        $count = 0;

        Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('ligne_budgetaire_id')
            ->with('ligneBudgetaire.sourcesFinancement')
            ->each(function (Activite $activite) use ($dryRun, &$count) {
                $ligne = $activite->ligneBudgetaire;
                if (! $ligne || (float) $ligne->montant_alloue <= 0) {
                    return;
                }

                $ceeac = (float) $activite->montant_ceeac;
                $ptf = (float) $activite->montant_ptf;
                if ($ceeac <= 0 && $ptf <= 0) {
                    return;
                }

                $pivotTotal = (float) $ligne->sourcesFinancement->sum('montant');
                $alloue = (float) $ligne->montant_alloue;

                $incoherent = $pivotTotal <= 0 || abs($pivotTotal - $alloue) > 0.05;

                if (! $incoherent) {
                    return;
                }

                if ($dryRun) {
                    $count++;

                    return;
                }

                $this->sync->synchroniserActivite($activite->fresh());
                $count++;
            });

        return $count;
    }

    private function corrigerLignesStructurelles(int $exerciceId, bool $dryRun): int
    {
        $count = 0;

        LigneBudgetaire::query()
            ->where('exercice_id', $exerciceId)
            ->whereNull('activite_id')
            ->where('montant_ouvert', '>', 0)
            ->whereNotNull('source_financement_id')
            ->with('sourcesFinancement')
            ->each(function (LigneBudgetaire $ligne) use ($dryRun, &$count) {
                $alloue = (float) $ligne->montant_alloue;
                $pivots = $ligne->sourcesFinancement;

                if ($pivots->isNotEmpty() && abs((float) $pivots->sum('montant') - $alloue) <= 0.05) {
                    return;
                }

                if ($dryRun) {
                    $count++;

                    return;
                }

                $this->financement->synchroniser($ligne, [[
                    'source_financement_id' => (int) $ligne->source_financement_id,
                    'montant' => $alloue,
                    'est_principale' => true,
                ]]);
                $count++;
            });

        return $count;
    }
}
