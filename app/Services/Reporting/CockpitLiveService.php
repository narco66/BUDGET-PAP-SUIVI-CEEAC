<?php

namespace App\Services\Reporting;

use App\Services\Pap\PapPilotageService;
use App\Support\CockpitCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** API live cockpit — snapshot, drill-down et version cache (P3-7). */
class CockpitLiveService
{
    public function __construct(
        private readonly ReportingService $reporting,
        private readonly PapPilotageService $pap,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(int $exerciceId): array
    {
        return CockpitCache::remember('live_snapshot', $exerciceId, function () use ($exerciceId) {
            $cockpit = $this->reporting->cockpit($exerciceId);
            $global = $cockpit['global'];

            return [
                'cache_version' => CockpitCache::version(),
                'exercice_id' => $exerciceId,
                'global' => [
                    'ouvert' => $global['ouvert'],
                    'engage' => $global['engage'],
                    'paye' => $global['paye'],
                    'taux_execution' => $global['taux_execution'],
                    'taux_engagement' => $global['taux_engagement'],
                    'reste_a_payer' => $global['reste_a_payer'] ?? max($global['ordonnance'] - $global['paye'], 0),
                    'alloue' => $global['alloue'] ?? $global['ouvert'],
                    'liquide' => $global['liquide'],
                    'ordonnance' => $global['ordonnance'],
                    'disponible' => $global['disponible'] ?? null,
                ],
                'chaine' => $cockpit['chaine'] ?? [],
                'pap' => $cockpit['pap'],
                'series' => [
                    'categories' => $cockpit['parCategorie']->map(fn ($r) => [
                        'code' => $r['code'] ?? '—',
                        'label' => trim(($r['code'] ?? '').' '.($r['intitule'] ?? '')),
                        'ouvert' => $r['ouvert'],
                        'paye' => $r['paye'],
                    ])->values()->all(),
                    'axes' => $cockpit['parAxe']->map(fn ($r) => [
                        'code' => $r['code'] ?? '—',
                        'label' => $r['code'] ?? '—',
                        'ouvert' => $r['ouvert'],
                        'paye' => $r['paye'],
                    ])->values()->all(),
                    'structures' => $cockpit['parStructure']->map(fn ($r) => [
                        'label' => $r['libelle'],
                        'ouvert' => $r['ouvert'],
                        'paye' => $r['paye'],
                    ])->values()->all(),
                ],
                'alertes_count' => count($cockpit['alertes']),
            ];
        });
    }

    /** @return array<string, mixed> */
    public function drilldown(int $exerciceId, string $niveau, ?string $parent = null): array
    {
        return CockpitCache::remember("live_drill_{$niveau}_".md5((string) $parent), $exerciceId, function () use ($exerciceId, $niveau, $parent) {
            $lignes = match ($niveau) {
                'categorie' => $this->drillCategorie($exerciceId, $parent),
                'axe' => $this->drillAxe($exerciceId, $parent),
                'structure' => $this->drillStructure($exerciceId, $parent),
                'pilier' => $this->drillPilier($exerciceId, $parent),
                default => throw new InvalidArgumentException("Niveau drill-down inconnu : {$niveau}"),
            };

            return [
                'niveau' => $niveau,
                'parent' => $parent,
                'cache_version' => CockpitCache::version(),
                'lignes' => $lignes,
            ];
        });
    }

    /** @return array{changed: bool, cache_version: int} */
    public function ping(int $sinceVersion): array
    {
        $current = CockpitCache::version();

        return [
            'changed' => $current > $sinceVersion,
            'cache_version' => $current,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function drillCategorie(int $exerciceId, ?string $parent): array
    {
        abort_if(blank($parent), 422, 'Code titre requis pour le drill-down catégorie.');

        return $this->reporting->categorieDetail($exerciceId, $parent)->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function drillAxe(int $exerciceId, ?string $parent): array
    {
        abort_if(blank($parent), 422, 'Code axe requis.');

        return DB::table('produits as pr')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as pil', 'ax.pilier_id', '=', 'pil.id')
            ->leftJoin('sous_produits as sp', 'sp.produit_id', '=', 'pr.id')
            ->leftJoin('activites as a', function ($j) {
                $j->on('a.sous_produit_id', '=', 'sp.id')->whereNull('a.deleted_at');
            })
            ->leftJoin('lignes_budgetaires as lb', 'a.ligne_budgetaire_id', '=', 'lb.id')
            ->where('pil.exercice_id', $exerciceId)
            ->where('ax.code', $parent)
            ->whereNull('pr.deleted_at')
            ->groupBy('pr.id', 'pr.code', 'pr.intitule')
            ->selectRaw('pr.code, pr.intitule,
                COUNT(DISTINCT a.id) activites,
                COALESCE(SUM(lb.montant_ouvert),0) ouvert,
                COALESCE(SUM(lb.montant_paye),0) paye')
            ->orderBy('pr.code')
            ->get()
            ->map(fn ($r) => [
                'code' => $r->code,
                'intitule' => $r->intitule,
                'activites' => (int) $r->activites,
                'ouvert' => (float) $r->ouvert,
                'paye' => (float) $r->paye,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function drillStructure(int $exerciceId, ?string $parent): array
    {
        abort_if(blank($parent), 422, 'Structure requise.');

        return DB::table('lignes_budgetaires as lb')
            ->join('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->leftJoin('structures as s', 'lb.structure_id', '=', 's.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->where(function ($q) use ($parent) {
                $q->where('s.sigle', $parent)->orWhere('s.libelle', $parent);
            })
            ->groupBy('r.id', 'r.code', 'r.intitule')
            ->selectRaw('r.code, r.intitule,
                COALESCE(SUM(lb.montant_ouvert),0) ouvert,
                COALESCE(SUM(lb.montant_engage),0) engage,
                COALESCE(SUM(lb.montant_paye),0) paye')
            ->orderBy('r.code')
            ->get()
            ->map(fn ($r) => [
                'code' => $r->code,
                'intitule' => $r->intitule,
                'ouvert' => (float) $r->ouvert,
                'engage' => (float) $r->engage,
                'paye' => (float) $r->paye,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function drillPilier(int $exerciceId, ?string $parent): array
    {
        abort_if(blank($parent), 422, 'Code pilier requis.');

        return $this->pap->performancePiliers($exerciceId)
            ->when($parent, fn (Collection $c) => $c->where('code', $parent))
            ->values()
            ->all();
    }
}
