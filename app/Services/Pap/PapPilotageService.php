<?php

namespace App\Services\Pap;

use App\Support\CockpitCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Tableau de pilotage PAP unifié (phase P1 — KPI, drill-down, alertes). */
class PapPilotageService
{
    public function __construct(
        private readonly PapPerformanceService $performance,
        private readonly PapAuditService $audit,
    ) {
    }

    /**
     * Tableau complet, mis en cache sous la génération de cockpit : toute écriture sur une activité,
     * une ligne budgétaire ou une mesure l'invalide (CockpitCache::bump), les montants restent exacts.
     *
     * @return array<string,mixed>
     */
    public function tableau(int $exerciceId): array
    {
        return CockpitCache::remember('pap_pilotage', $exerciceId, fn () => $this->performance->memoriser(
            fn () => $this->calculerTableau($exerciceId)
        ));
    }

    /** @return array<string,mixed> */
    private function calculerTableau(int $exerciceId): array
    {
        $synthese = $this->performance->executionPap($exerciceId);
        $pilierParActivite = $this->pilierParActivite($exerciceId);
        $activites = $this->performance->performanceActivites($exerciceId)
            ->map(fn (array $a) => $a + ['pilier_code' => $pilierParActivite[$a['activite_id']] ?? null]);
        $alertes = $this->alertesDepuis($activites);

        return [
            'synthese' => $synthese,
            'piliers' => $this->performancePiliers($exerciceId),
            'produits' => $this->performance->performanceProduits($exerciceId),
            'activites' => $activites,
            'departements' => $this->performance->classementDepartements($exerciceId),
            'structures' => $this->performance->consolidationParStructure($exerciceId),
            'alertes' => $alertes,
            'audit' => $this->audit->executer($exerciceId),
            'indicateurs' => [
                'activites_en_retard' => $alertes['en_retard']->count(),
                'activites_surconsommation' => $alertes['surconsommation']->count(),
                'indice_global' => round(
                    ($synthese['taux_physique'] + $synthese['taux_financier']) / 2,
                    1
                ),
            ],
        ];
    }

    /** @return array<int,string> Code du pilier de chaque activité de l'exercice (filtre drill-down). */
    private function pilierParActivite(int $exerciceId): array
    {
        return DB::table('activites as a')
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->where('p.exercice_id', $exerciceId)
            ->pluck('p.code', 'a.id')
            ->all();
    }

    /** Performance consolidée par pilier (drill-down niveau 1). */
    public function performancePiliers(int $exerciceId): Collection
    {
        return DB::table('piliers as p')
            ->leftJoin('axes as ax', 'ax.pilier_id', '=', 'p.id')
            ->leftJoin('produits as pr', 'pr.axe_id', '=', 'ax.id')
            ->leftJoin('sous_produits as sp', 'sp.produit_id', '=', 'pr.id')
            ->leftJoin('activites as a', function ($j) {
                $j->on('a.sous_produit_id', '=', 'sp.id')->whereNull('a.deleted_at');
            })
            ->leftJoin('lignes_budgetaires as lb', 'a.ligne_budgetaire_id', '=', 'lb.id')
            ->where('p.exercice_id', $exerciceId)
            ->whereNull('p.deleted_at')
            ->groupBy('p.id', 'p.code', 'p.intitule')
            ->selectRaw('p.code, p.intitule,
                COUNT(DISTINCT a.id) activites,
                COALESCE(SUM(a.montant_total),0) budget_prevu,
                COALESCE(SUM(lb.montant_paye),0) budget_paye,
                COALESCE(SUM(a.montant_ceeac),0) montant_ceeac,
                COALESCE(SUM(a.montant_ptf),0) montant_ptf,
                AVG(a.taux_realisation_physique) taux_physique')
            ->orderBy('p.code')
            ->get()
            ->map(function ($r) {
                $prevu = (float) $r->budget_prevu;
                $phys = round((float) $r->taux_physique, 1);
                $fin = $prevu > 0 ? round((float) $r->budget_paye / $prevu * 100, 1) : 0.0;

                return [
                    'code' => $r->code,
                    'intitule' => $r->intitule,
                    'activites' => (int) $r->activites,
                    'budget_prevu' => $prevu,
                    'budget_paye' => (float) $r->budget_paye,
                    'montant_ceeac' => (float) $r->montant_ceeac,
                    'montant_ptf' => (float) $r->montant_ptf,
                    'taux_physique' => $phys,
                    'taux_financier' => $fin,
                    'indice_performance' => round(($phys + $fin) / 2, 1),
                ];
            });
    }

    /** @return array{en_retard: Collection, surconsommation: Collection, total_alertes: int} */
    public function alertes(int $exerciceId, float $seuilRetard = 50): array
    {
        return $this->alertesDepuis($this->performance->performanceActivites($exerciceId), $seuilRetard);
    }

    /** @return array{en_retard: Collection, surconsommation: Collection, total_alertes: int} */
    private function alertesDepuis(Collection $activites, float $seuilRetard = 50): array
    {
        $enRetard = $activites->filter(fn (array $a) => $a['en_retard'] || $a['taux_physique'] < $seuilRetard)->values();
        $surconsommation = $activites->filter(fn (array $a) => $a['surconsommation'])->values();

        return [
            'en_retard' => $enRetard,
            'surconsommation' => $surconsommation,
            'total_alertes' => $enRetard->count() + $surconsommation->count(),
        ];
    }
}
