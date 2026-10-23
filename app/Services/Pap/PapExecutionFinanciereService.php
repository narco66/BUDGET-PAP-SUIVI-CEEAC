<?php

namespace App\Services\Pap;

use App\Models\Activite;
use App\Models\EngagementImputation;
use Illuminate\Support\Collection;

/** Chaîne dépense PAP : activité → ligne → engagement → liquidation → ordonnancement → paiement (P4). */
class PapExecutionFinanciereService
{
    /** @return array{synthese: array<string,mixed>, activites: Collection<int, array<string,mixed>>} */
    public function chainePourExercice(int $exerciceId): array
    {
        $activites = Activite::query()
            ->with(['ligneBudgetaire', 'structure'])
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->orderBy('code')
            ->get();

        $ligneIds = $activites->pluck('ligne_budgetaire_id')->filter()->unique()->values();

        $imputationsParLigne = $ligneIds->isEmpty()
            ? collect()
            : EngagementImputation::query()
                ->whereIn('ligne_budgetaire_id', $ligneIds)
                ->with([
                    'engagement.liquidations' => fn ($q) => $q->whereNotIn('statut', ['rejete', 'annule']),
                    'engagement.liquidations.ordonnancements' => fn ($q) => $q->whereNotIn('statut', ['rejete', 'annule']),
                    'engagement.liquidations.ordonnancements.paiements' => fn ($q) => $q->whereNotIn('statut', ['rejete', 'annule']),
                ])
                ->get()
                ->groupBy('ligne_budgetaire_id');

        $lignes = $activites->map(fn (Activite $a) => $this->noeudActivite($a, $imputationsParLigne));

        return [
            'synthese' => [
                'activites' => $activites->count(),
                'avec_ligne' => $activites->whereNotNull('ligne_budgetaire_id')->count(),
                'avec_engagement' => $lignes->filter(fn ($n) => $n['nb_engagements'] > 0)->count(),
                'budget_prevu' => round($lignes->sum('montant_prevu'), 2),
                'montant_engage' => round($lignes->sum('montant_engage'), 2),
                'montant_liquide' => round($lignes->sum('montant_liquide'), 2),
                'montant_ordonnance' => round($lignes->sum('montant_ordonnance'), 2),
                'montant_paye' => round($lignes->sum('montant_paye'), 2),
            ],
            'activites' => $lignes,
        ];
    }

    /** @param  Collection<int, Collection<int, EngagementImputation>>  $imputationsParLigne */
    /** @return array<string,mixed> */
    private function noeudActivite(Activite $activite, Collection $imputationsParLigne): array
    {
        $lb = $activite->ligneBudgetaire;
        $imputations = $lb ? ($imputationsParLigne->get($lb->id) ?? collect()) : collect();

        $engagements = [];
        $montantEngage = 0.0;
        $montantLiquide = 0.0;
        $montantOrdonnance = 0.0;
        $montantPaye = 0.0;

        foreach ($imputations as $imp) {
            $eng = $imp->engagement;
            if (! $eng || in_array($eng->statut, ['rejete', 'annule'], true)) {
                continue;
            }

            $liqs = [];
            foreach ($eng->liquidations as $liq) {
                $ords = [];
                $liqMontant = (float) $liq->montant;
                if (in_array($liq->statut, ['valide', 'vise'], true)) {
                    $montantLiquide += $liqMontant;
                }

                foreach ($liq->ordonnancements as $ord) {
                    $pays = [];
                    $ordMontant = (float) $ord->montant;
                    if (in_array($ord->statut, ['valide', 'vise'], true)) {
                        $montantOrdonnance += $ordMontant;
                    }

                    foreach ($ord->paiements as $pay) {
                        $payMontant = (float) $pay->montant;
                        if ($pay->statut === 'valide') {
                            $montantPaye += $payMontant;
                        }
                        $pays[] = [
                            'numero' => $pay->numero,
                            'montant' => $payMontant,
                            'statut' => $pay->statut,
                            'date' => $pay->date_paiement?->format('d/m/Y'),
                        ];
                    }

                    $ords[] = [
                        'numero' => $ord->numero,
                        'montant' => $ordMontant,
                        'statut' => $ord->statut,
                        'paiements' => $pays,
                    ];
                }

                $liqs[] = [
                    'numero' => $liq->numero,
                    'montant' => $liqMontant,
                    'statut' => $liq->statut,
                    'ordonnancements' => $ords,
                ];
            }

            $impMontant = (float) $imp->montant;
            if (in_array($eng->statut, ['vise', 'valide'], true)) {
                $montantEngage += $impMontant;
            }

            $engagements[] = [
                'id' => $eng->id,
                'numero' => $eng->numero,
                'objet' => $eng->objet,
                'montant_impute' => $impMontant,
                'statut' => $eng->statut,
                'liquidations' => $liqs,
            ];
        }

        $prevu = (float) ($lb?->montant_ouvert ?? $activite->montant_total);

        return [
            'id' => $activite->id,
            'code' => $activite->code,
            'intitule' => $activite->intitule,
            'structure' => $activite->structure?->libelle,
            'ligne_code' => $lb?->code,
            'montant_prevu' => $prevu,
            'montant_engage' => $montantEngage,
            'montant_liquide' => $montantLiquide,
            'montant_ordonnance' => $montantOrdonnance,
            'montant_paye' => $montantPaye,
            'taux_execution' => $prevu > 0 ? round($montantPaye / $prevu * 100, 1) : 0.0,
            'nb_engagements' => count($engagements),
            'engagements' => $engagements,
        ];
    }
}
