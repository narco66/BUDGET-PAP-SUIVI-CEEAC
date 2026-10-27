<?php

namespace App\Services;

use App\Models\Engagement;
use App\Services\Budget\SuiviFinancierCalculator;
use App\Support\CockpitCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Agrégations de pilotage (D10) — partagées par les tableaux de bord et les rapports (D9). */
class TableauBordService
{
    /** Synthèse d'exécution budgétaire (compteurs + taux). */
    public function executionBudgetaire(int $exerciceId): array
    {
        return CockpitCache::remember('exec', $exerciceId, function () use ($exerciceId) {
            $t = DB::table('lignes_budgetaires')->where('exercice_id', $exerciceId)->whereNull('deleted_at')
                ->selectRaw('COALESCE(SUM(COALESCE(montant_revise, montant_ouvert)),0) alloue,
                             COALESCE(SUM(montant_ouvert),0) ouvert,
                             COALESCE(SUM(montant_engage),0) engage,
                             COALESCE(SUM(montant_liquide),0) liquide, COALESCE(SUM(montant_ordonnance),0) ordonnance,
                             COALESCE(SUM(montant_paye),0) paye')
                ->first();

            $alloue = (float) $t->alloue;
            $ouvert = (float) $t->ouvert;
            $s = SuiviFinancierCalculator::synthese(
                $ouvert,
                (float) $t->engage,
                (float) $t->liquide,
                (float) $t->ordonnance,
                (float) $t->paye,
                $alloue,
            );

            return array_merge($s, ['revise' => $s['revise_delta']]);
        });
    }

    /** Consommation par nature (Titre de la nomenclature). */
    public function consommationParNature(int $exerciceId): Collection
    {
        return CockpitCache::remember('nature', $exerciceId, fn () => DB::table('lignes_budgetaires as lb')
            ->join('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->join('budget_paragraphes as pa', 'r.paragraphe_id', '=', 'pa.id')
            ->join('budget_articles as a', 'pa.article_id', '=', 'a.id')
            ->join('budget_titres as t', 'a.titre_id', '=', 't.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')->where('t.sens', 'depense')
            ->groupBy('t.id', 't.code', 't.intitule')
            ->selectRaw('t.code, t.intitule, SUM(lb.montant_ouvert) ouvert, SUM(lb.montant_engage) engage, SUM(lb.montant_paye) paye')
            ->orderBy('t.code')->get());
    }

    /** Consommation par structure de rattachement. */
    public function consommationParStructure(int $exerciceId): Collection
    {
        return CockpitCache::remember('structure', $exerciceId, fn () => DB::table('lignes_budgetaires as lb')
            ->leftJoin('structures as s', 'lb.structure_id', '=', 's.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')
            ->groupBy('s.id', 's.libelle', 's.sigle')
            ->selectRaw('COALESCE(s.sigle, s.libelle, \'(non rattachée)\') libelle, SUM(lb.montant_ouvert) ouvert, SUM(lb.montant_engage) engage, SUM(lb.montant_paye) paye')
            ->orderByDesc('ouvert')->limit(15)->get());
    }

    /** Exécution du PAP : taux physique (collectes) + financier réel (lignes budgétaires payées). */
    public function executionPap(int $exerciceId): array
    {
        return CockpitCache::remember('pap', $exerciceId, fn () => app(\App\Services\Pap\PapPerformanceService::class)->executionPap($exerciceId));
    }

    /** Engagements par statut (nombre + montant). */
    public function engagementsParStatut(int $exerciceId): Collection
    {
        return CockpitCache::remember('engagements', $exerciceId, fn () => Engagement::where('exercice_id', $exerciceId)
            ->groupBy('statut')
            ->selectRaw('statut, COUNT(*) nombre, SUM(montant_total) montant')
            ->get());
    }

    /** Synthèse du PAP par pilier (montant total des activités + nombre). */
    public function papParPilier(int $exerciceId): Collection
    {
        return CockpitCache::remember('pilier', $exerciceId, fn () => DB::table('piliers as p')
            ->leftJoin('axes as ax', 'ax.pilier_id', '=', 'p.id')
            ->leftJoin('produits as pr', 'pr.axe_id', '=', 'ax.id')
            ->leftJoin('sous_produits as sp', 'sp.produit_id', '=', 'pr.id')
            ->leftJoin('activites as a', function ($j) {
                $j->on('a.sous_produit_id', '=', 'sp.id')->whereNull('a.deleted_at');
            })
            ->where('p.exercice_id', $exerciceId)->whereNull('p.deleted_at')
            ->groupBy('p.id', 'p.code', 'p.intitule')
            ->selectRaw('p.code, p.intitule, COUNT(DISTINCT a.id) activites,
                         COALESCE(SUM(a.montant_total),0) montant,
                         COALESCE(SUM(a.montant_ceeac),0) ceeac, COALESCE(SUM(a.montant_ptf),0) ptf')
            ->orderBy('p.code')->get());
    }

    /** Performance des indicateurs (répartition des feux). */
    public function feuxIndicateurs(?int $exerciceId = null): array
    {
        return CockpitCache::remember('feux', $exerciceId, fn () => app(\App\Services\Rbm\IndicateurPerformanceService::class)->feuxGlobaux($exerciceId));
    }
}
