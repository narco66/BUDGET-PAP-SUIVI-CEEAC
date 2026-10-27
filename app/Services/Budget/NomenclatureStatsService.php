<?php

namespace App\Services\Budget;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques de pilotage de la nomenclature budgétaire (L3) : volumétrie du référentiel et
 * analyse d'usage des lignes budgétaires (utilisées / inutilisées / les plus sollicitées).
 * Lecture seule, sans effet de bord.
 */
class NomenclatureStatsService
{
    /** Volumétrie du référentiel (éléments actifs) + répartition des titres par sens. */
    public function comptes(): array
    {
        return [
            'titres' => BudgetTitre::count(),
            'articles' => BudgetArticle::count(),
            'paragraphes' => BudgetParagraphe::count(),
            'rubriques' => BudgetRubrique::count(),
            'titres_par_sens' => BudgetTitre::query()
                ->select('sens', DB::raw('COUNT(*) as total'))
                ->groupBy('sens')->pluck('total', 'sens')->all(),
        ];
    }

    /** Synthèse d'usage des lignes budgétaires d'un exercice (engagées vs non engagées). */
    public function usageLignes(int $exerciceId): array
    {
        $row = DB::table('lignes_budgetaires')
            ->where('exercice_id', $exerciceId)->whereNull('deleted_at')
            ->selectRaw('COUNT(*) total')
            ->selectRaw('SUM(CASE WHEN montant_engage > 0 THEN 1 ELSE 0 END) utilisees')
            ->selectRaw('COALESCE(SUM(montant_ouvert),0) ouvert')
            ->selectRaw('COALESCE(SUM(montant_engage),0) engage')
            ->first();

        $total = (int) ($row->total ?? 0);
        $utilisees = (int) ($row->utilisees ?? 0);

        return [
            'total' => $total,
            'utilisees' => $utilisees,
            'inutilisees' => $total - $utilisees,
            'taux_utilisation' => $total > 0 ? round($utilisees / $total * 100, 1) : 0.0,
            'montant_ouvert' => (float) ($row->ouvert ?? 0),
            'montant_engage' => (float) ($row->engage ?? 0),
        ];
    }

    /** Lignes budgétaires non engagées (montant_engage = 0), dotées d'un crédit ouvert. */
    public function lignesInutilisees(int $exerciceId, int $limite = 20): Collection
    {
        return DB::table('lignes_budgetaires as lb')
            ->leftJoin('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->leftJoin('structures as s', 'lb.structure_id', '=', 's.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')
            ->where('lb.montant_engage', '<=', 0)
            ->selectRaw('lb.code, lb.intitule, lb.montant_ouvert, r.code rubrique_code,
                         COALESCE(s.sigle, s.libelle, \'—\') structure')
            ->orderByDesc('lb.montant_ouvert')
            ->limit($limite)->get();
    }

    /** Lignes budgétaires les plus sollicitées (nombre d'imputations d'engagement décroissant). */
    public function lignesPlusSollicitees(int $exerciceId, int $limite = 20): Collection
    {
        return DB::table('lignes_budgetaires as lb')
            ->join('engagement_imputations as ei', 'ei.ligne_budgetaire_id', '=', 'lb.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')
            ->groupBy('lb.id', 'lb.code', 'lb.intitule', 'lb.montant_engage')
            ->selectRaw('lb.code, lb.intitule, lb.montant_engage,
                         COUNT(ei.id) nb_imputations, COALESCE(SUM(ei.montant),0) montant_impute')
            ->orderByDesc('nb_imputations')->orderByDesc('montant_impute')
            ->limit($limite)->get();
    }
}
