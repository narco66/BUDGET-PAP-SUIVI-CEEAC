<?php

namespace App\Services\Budget;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Services\Depense\ImputationPapService;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;

/** Rapprochement PAP (activités RBM) ↔ lignes budgétaires après imports séparés. */
class PapBudgetRapprochementService
{
    public function __construct(private readonly RbmBudgetSyncService $sync)
    {
    }

    /** @return array<string,mixed> */
    public function analyser(int $exerciceId): array
    {
        $activites = $this->activitesExercice($exerciceId);
        $lignes = LigneBudgetaire::query()
            ->where('exercice_id', $exerciceId)
            ->with('activite')
            ->get();

        $lignesParCode = $lignes->keyBy('code');
        $ecarts = [];

        foreach ($activites as $activite) {
            $montantPap = (float) $activite->montant_total;
            if ($montantPap <= 0) {
                continue;
            }

            if (! $activite->ligne_budgetaire_id) {
                $ligneCorrespondante = $lignesParCode->get($activite->code);
                if ($ligneCorrespondante) {
                    $ecarts[] = $this->ecart('LIAISON_POSSIBLE', $activite, $ligneCorrespondante, $montantPap);
                } else {
                    $ecarts[] = $this->ecart('ACTIVITE_SANS_LIGNE', $activite, null, $montantPap);
                }

                continue;
            }

            $ligne = $activite->ligneBudgetaire;
            if (! $ligne) {
                $ecarts[] = $this->ecart('LIGNE_INEXISTANTE', $activite, null, $montantPap);

                continue;
            }

            // Enveloppe PAP « ACT-{axe} » partagée par plusieurs activités : ni lien inverse unique (règle de
            // RbmBudgetSyncService), ni égalité de montant avec une activité — ce ne sont pas des écarts.
            if (ImputationPapService::estEnveloppePap($ligne)) {
                continue;
            }

            if ((int) $ligne->activite_id !== (int) $activite->id) {
                $ecarts[] = $this->ecart('LIAISON_DESYNC', $activite, $ligne, $montantPap);
            }

            $montantLigne = (float) $ligne->montant_alloue;
            if (abs($montantPap - $montantLigne) > 1) {
                $ecarts[] = array_merge(
                    $this->ecart('MONTANT_ECART', $activite, $ligne, $montantPap),
                    ['montant_ligne' => $montantLigne, 'ecart' => round($montantPap - $montantLigne, 2)]
                );
            }
        }

        foreach ($lignes as $ligne) {
            if ((float) $ligne->montant_alloue <= 0 || $ligne->activite_id) {
                continue;
            }

            $activiteCorrespondante = $activites->firstWhere('code', $ligne->code);
            if (! $activiteCorrespondante || ! $activiteCorrespondante->ligne_budgetaire_id) {
                continue;
            }

            if ((int) $activiteCorrespondante->ligne_budgetaire_id !== (int) $ligne->id) {
                $ecarts[] = $this->ecart('LIGNE_SANS_LIAISON', $activiteCorrespondante, $ligne, (float) $activiteCorrespondante->montant_total);
            }
        }

        $activitesAvecMontant = $activites->filter(fn (Activite $a) => (float) $a->montant_total > 0);
        $totalPap = (float) $activitesAvecMontant->sum('montant_total');
        // Chaque ligne comptée une fois : une enveloppe d'axe partagée par plusieurs activités était additionnée
        // autant de fois qu'elle avait d'activités rattachées (écart global faussé).
        $totalLignesLiees = (float) $activitesAvecMontant
            ->filter(fn (Activite $a) => $a->ligne_budgetaire_id && $a->ligneBudgetaire)
            ->unique('ligne_budgetaire_id')
            ->sum(fn (Activite $a) => (float) $a->ligneBudgetaire->montant_alloue);

        return [
            'exercice_id' => $exerciceId,
            'genere_le' => now()->toIso8601String(),
            'synthese' => [
                'activites_avec_montant' => $activitesAvecMontant->count(),
                'activites_liees' => $activitesAvecMontant->whereNotNull('ligne_budgetaire_id')->count(),
                'lignes_budget' => $lignes->count(),
                'total_montant_pap' => $totalPap,
                'total_montant_lignes_liees' => $totalLignesLiees,
                'ecart_global' => round($totalPap - $totalLignesLiees, 2),
            ],
            'ecarts_par_type' => collect($ecarts)->countBy('type')->all(),
            'ecarts' => $ecarts,
            'ecarts_total' => count($ecarts),
        ];
    }

    /**
     * @param  array{lier_codes?:bool,aligner_montants?:bool,dry_run?:bool}  $options
     * @return array<string,int>
     */
    public function rapprocher(int $exerciceId, array $options = []): array
    {
        $lierCodes = $options['lier_codes'] ?? true;
        $alignerMontants = $options['aligner_montants'] ?? false;
        $dryRun = $options['dry_run'] ?? false;

        $stats = [
            'liaisons_creees' => 0,
            'liaisons_sync' => 0,
            'montants_alignes' => 0,
            'montants_ignores' => 0,
        ];

        $analyse = $this->analyser($exerciceId);

        if ($lierCodes) {
            foreach ($analyse['ecarts'] as $ecart) {
                if ($ecart['type'] !== 'LIAISON_POSSIBLE') {
                    continue;
                }

                $activite = Activite::find($ecart['activite_id']);
                $ligne = LigneBudgetaire::find($ecart['ligne_id']);
                if (! $activite || ! $ligne) {
                    continue;
                }

                if ($dryRun) {
                    $stats['liaisons_creees']++;

                    continue;
                }

                $activite->update(['ligne_budgetaire_id' => $ligne->id]);
                $this->sync->synchroniserActivite($activite->fresh());
                $stats['liaisons_creees']++;
            }
        }

        $activites = $this->activitesExercice($exerciceId)->whereNotNull('ligne_budgetaire_id');
        foreach ($activites as $activite) {
            $ligne = $activite->ligneBudgetaire;
            if (! $ligne || (int) $ligne->activite_id === (int) $activite->id || ImputationPapService::estEnveloppePap($ligne)) {
                continue;   // une enveloppe partagée n'a pas de lien inverse : rien à synchroniser
            }

            if ($dryRun) {
                $stats['liaisons_sync']++;

                continue;
            }

            $this->sync->synchroniserActivite($activite);
            $stats['liaisons_sync']++;
        }

        if ($alignerMontants) {
            $analyse = $this->analyser($exerciceId);
            foreach ($analyse['ecarts'] as $ecart) {
                if ($ecart['type'] !== 'MONTANT_ECART') {
                    continue;
                }

                $ligne = LigneBudgetaire::find($ecart['ligne_id']);
                // Garde-fou : le crédit d'une enveloppe d'axe n'est jamais réécrit avec le montant d'une activité.
                if (! $ligne || (float) $ligne->montant_engage > 0 || ImputationPapService::estEnveloppePap($ligne)) {
                    $stats['montants_ignores']++;

                    continue;
                }

                if ($dryRun) {
                    $stats['montants_alignes']++;

                    continue;
                }

                $ligne->update(['montant_ouvert' => (float) $ecart['montant_pap']]);
                AuditLogger::enregistrer('pap_rapprochement_montant', $ligne, null, [
                    'activite_code' => $ecart['activite_code'],
                    'ancien' => (float) $ecart['montant_ligne'],
                    'nouveau' => (float) $ecart['montant_pap'],
                ]);
                $stats['montants_alignes']++;
            }
        }

        if (! $dryRun && array_sum($stats) > 0) {
            AuditLogger::enregistrer('pap_rapprochement', Exercice::find($exerciceId), null, $stats);
            app(\App\Services\Pap\PapPerformanceService::class)->synchroniserTauxFinanciersExercice($exerciceId);
        }

        return $stats;
    }

    /** @return Collection<int,Activite> */
    private function activitesExercice(int $exerciceId): Collection
    {
        return Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->with('ligneBudgetaire')
            ->get();
    }

    /** @return array<string,mixed> */
    private function ecart(string $type, Activite $activite, ?LigneBudgetaire $ligne, float $montantPap): array
    {
        return [
            'type' => $type,
            'activite_id' => $activite->id,
            'activite_code' => $activite->code,
            'activite_intitule' => $activite->intitule,
            'ligne_id' => $ligne?->id,
            'ligne_code' => $ligne?->code,
            'montant_pap' => $montantPap,
        ];
    }
}
