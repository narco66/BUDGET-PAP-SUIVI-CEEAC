<?php

namespace App\Services\Tresorerie;

use App\Models\Encaissement;
use App\Models\Paiement;
use App\Services\Recettes\GestionRecettesService;
use App\Services\Recettes\RecettesReportingService;
use App\Services\TableauBordService;
use App\Support\CockpitCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Dashboard trésorerie unifié recettes / dépenses (P2-5). */
class TresorerieService
{
    public function __construct(
        private readonly TableauBordService $tableauBord,
        private readonly GestionRecettesService $recettes,
        private readonly RecettesReportingService $reportingRecettes,
    ) {}

    /** @return array<string, mixed> */
    public function synthese(int $exerciceId): array
    {
        return CockpitCache::remember('tresorerie', $exerciceId, function () use ($exerciceId) {
            $budget = $this->tableauBord->executionBudgetaire($exerciceId);
            $recettes = $this->recettes->syntheseExercice($exerciceId);
            $mobilisation = $this->reportingRecettes->syntheseMobilisation($exerciceId);

            $encaisse = $this->totalEncaissements($exerciceId);
            $decaisse = $this->totalPaiements($exerciceId);
            $fluxNet = $encaisse - $decaisse;

            $resteRecouvrer = max((float) $mobilisation['appele'] - (float) $mobilisation['recouvre'], 0);

            return [
                'recettes' => [
                    'prevu' => (float) $recettes['montant_prevu'],
                    'realise' => (float) $recettes['montant_realise'],
                    'encaisse' => $encaisse,
                    'appele' => (float) $mobilisation['appele'],
                    'recouvre' => (float) $mobilisation['recouvre'],
                    'arriere' => (float) $mobilisation['arriere'],
                    'reste_a_recouvrer' => $resteRecouvrer,
                    'taux_realisation' => $recettes['montant_prevu'] > 0
                        ? round($recettes['montant_realise'] / $recettes['montant_prevu'] * 100, 1) : 0.0,
                    'taux_encaissement' => $recettes['montant_realise'] > 0
                        ? round($encaisse / $recettes['montant_realise'] * 100, 1) : 0.0,
                ],
                'depenses' => [
                    'ouvert' => (float) $budget['ouvert'],
                    'engage' => (float) $budget['engage'],
                    'ordonnance' => (float) ($budget['ordonnance'] ?? 0),
                    'paye' => (float) $budget['paye'],
                    'decaisse' => $decaisse,
                    'reste_a_engager' => (float) ($budget['reste_a_engager'] ?? 0),
                    'reste_a_payer' => (float) ($budget['reste_a_payer'] ?? 0),
                    'taux_execution' => (float) ($budget['taux_execution'] ?? 0),
                ],
                'flux' => [
                    'encaissements' => $encaisse,
                    'decaissements' => $decaisse,
                    'net' => $fluxNet,
                    'couverture_paiements' => $decaisse > 0 ? round($encaisse / $decaisse * 100, 1) : null,
                ],
                'equilibre' => [
                    'position_nette' => $fluxNet,
                    'alerte' => $this->alerte($encaisse, $decaisse, (float) ($budget['reste_a_payer'] ?? 0), $resteRecouvrer),
                ],
                'evolution_mensuelle' => $this->evolutionMensuelle($exerciceId),
                'par_sens' => $this->ventilationParSens($exerciceId),
            ];
        });
    }

    /** Journal des encaissements comptabilisés (rapport flux). */
    public function journalEncaissements(int $exerciceId): Collection
    {
        return Encaissement::query()
            ->with('recette:id,reference')
            ->whereIn('statut', Encaissement::STATUTS_VERROUILLES)
            ->whereHas('recette', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->orderBy('date_encaissement')
            ->get(['id', 'recette_id', 'reference', 'montant', 'montant_xaf', 'mode_paiement', 'date_encaissement', 'statut'])
            ->map(fn (Encaissement $e) => [
                'reference' => $e->reference,
                'recette' => $e->recette?->reference ?? '—',
                'date' => $e->date_encaissement?->format('Y-m-d') ?? '—',
                'montant' => $this->montantEffectif($e),
                'mode' => Encaissement::MODES_PAIEMENT[$e->mode_paiement] ?? $e->mode_paiement,
                'statut' => Encaissement::STATUTS[$e->statut] ?? $e->statut,
            ]);
    }

    /** Journal des paiements validés (rapport flux). */
    public function journalPaiements(int $exerciceId): Collection
    {
        return Paiement::query()
            ->with('ordonnancement.liquidation.engagement:id,numero,objet,beneficiaire')
            ->where('statut', 'valide')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->orderBy('date_paiement')
            ->get(['id', 'ordonnancement_id', 'numero', 'montant', 'montant_xaf', 'mode_paiement', 'date_paiement', 'statut'])
            ->map(function (Paiement $p) {
                $eng = $p->ordonnancement?->liquidation?->engagement;

                return [
                    'numero' => $p->numero,
                    'engagement' => $eng?->numero ?? '—',
                    'beneficiaire' => $eng?->beneficiaire ?? '—',
                    'objet' => $eng?->objet ?? '—',
                    'date' => $p->date_paiement?->format('Y-m-d') ?? '—',
                    'montant' => $this->montantEffectif($p),
                    'mode' => $p->mode_paiement,
                ];
            });
    }

    /** Restes à payer par engagement (détail dette fournisseurs). */
    public function restesAPayer(int $exerciceId): Collection
    {
        return CockpitCache::remember('tresorerie_restes', $exerciceId, fn () => $this->calculerRestesAPayer($exerciceId));
    }

    /** Dette agrégée par bénéficiaire / fournisseur. */
    public function detteParFournisseur(int $exerciceId): Collection
    {
        return $this->restesAPayer($exerciceId)
            ->filter(fn (array $r) => $r['reste'] > 0)
            ->groupBy(fn (array $r) => $r['beneficiaire'] ?: '—')
            ->map(fn (Collection $rows, string $fournisseur) => [
                'fournisseur' => $fournisseur,
                'engagements' => $rows->count(),
                'ordonnance' => round($rows->sum('ordonnance'), 2),
                'paye' => round($rows->sum('paye'), 2),
                'reste' => round($rows->sum('reste'), 2),
            ])
            ->sortByDesc('reste')
            ->values();
    }

    private function totalEncaissements(int $exerciceId): float
    {
        return (float) Encaissement::query()
            ->whereIn('statut', Encaissement::STATUTS_VERROUILLES)
            ->whereHas('recette', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->sum(DB::raw('CASE WHEN montant_xaf > 0 THEN montant_xaf ELSE montant END'));
    }

    private function totalPaiements(int $exerciceId): float
    {
        return (float) Paiement::query()
            ->where('statut', 'valide')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->sum(DB::raw('CASE WHEN montant_xaf > 0 THEN montant_xaf ELSE montant END'));
    }

    /** @return list<array{mois: string, entrees: float, sorties: float, net: float}> */
    private function evolutionMensuelle(int $exerciceId): array
    {
        $buckets = [];

        Encaissement::query()
            ->whereIn('statut', Encaissement::STATUTS_VERROUILLES)
            ->whereHas('recette', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('date_encaissement')
            ->get(['date_encaissement', 'montant', 'montant_xaf'])
            ->each(function (Encaissement $e) use (&$buckets) {
                $mois = $e->date_encaissement->format('Y-m');
                $buckets[$mois]['entrees'] = ($buckets[$mois]['entrees'] ?? 0) + $this->montantEffectif($e);
            });

        Paiement::query()
            ->where('statut', 'valide')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('date_paiement')
            ->get(['date_paiement', 'montant', 'montant_xaf'])
            ->each(function (Paiement $p) use (&$buckets) {
                $mois = $p->date_paiement->format('Y-m');
                $buckets[$mois]['sorties'] = ($buckets[$mois]['sorties'] ?? 0) + $this->montantEffectif($p);
            });

        ksort($buckets);

        return collect($buckets)->map(fn (array $v, string $mois) => [
            'mois' => $mois,
            'entrees' => round($v['entrees'] ?? 0, 2),
            'sorties' => round($v['sorties'] ?? 0, 2),
            'net' => round(($v['entrees'] ?? 0) - ($v['sorties'] ?? 0), 2),
        ])->values()->all();
    }

    /** @return array{recettes: float, depenses: float} */
    private function ventilationParSens(int $exerciceId): array
    {
        $rows = DB::table('lignes_budgetaires as lb')
            ->join('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->join('budget_paragraphes as pa', 'r.paragraphe_id', '=', 'pa.id')
            ->join('budget_articles as a', 'pa.article_id', '=', 'a.id')
            ->join('budget_titres as t', 'a.titre_id', '=', 't.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->groupBy('t.sens')
            ->selectRaw('t.sens, COALESCE(SUM(lb.montant_paye), 0) paye')
            ->pluck('paye', 'sens');

        return [
            'recettes' => (float) ($rows['recette'] ?? 0),
            'depenses' => (float) ($rows['depense'] ?? 0),
        ];
    }

    private function alerte(float $encaisse, float $decaisse, float $resteAPayer, float $resteRecouvrer): ?string
    {
        if ($decaisse > $encaisse && $decaisse > 0) {
            return 'Décaissements supérieurs aux encaissements comptabilisés sur l\'exercice.';
        }
        if ($resteAPayer > $encaisse * 1.5 && $resteAPayer > 0) {
            return 'Restes à payer élevés par rapport aux encaissements validés.';
        }
        if ($resteRecouvrer > $encaisse && $resteRecouvrer > 0) {
            return 'Arriérés de contributions supérieurs aux encaissements.';
        }

        return null;
    }

    private function montantEffectif(Encaissement|Paiement $mouvement): float
    {
        $xaf = (float) $mouvement->montant_xaf;

        return $xaf > 0 ? $xaf : (float) $mouvement->montant;
    }

    /** @return Collection<int, array{numero: string, objet: string, structure: string, beneficiaire: string, engage: float, liquide: float, ordonnance: float, paye: float, reste: float}> */
    private function calculerRestesAPayer(int $exerciceId): Collection
    {
        $ordonnance = DB::table('ordonnancements as o')
            ->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->leftJoin('expressions_besoin as eb', 'eb.id', '=', 'e.expression_besoin_id')
            ->leftJoin('structures as s', 's.id', '=', 'eb.structure_id')
            ->where('e.exercice_id', $exerciceId)
            ->where('o.statut', 'valide')
            ->whereNull('o.deleted_at')
            ->groupBy('e.id', 'e.numero', 'e.objet', 'e.beneficiaire', 'e.montant_total', 's.sigle', 's.libelle')
            ->selectRaw('e.id, e.numero, e.objet, e.beneficiaire, e.montant_total as engage,
                         COALESCE(s.sigle, s.libelle, \'—\') as structure,
                         COALESCE(SUM(o.montant),0) as ordonnance')
            ->get();

        $liquide = DB::table('liquidations as l')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->where('e.exercice_id', $exerciceId)
            ->where('l.statut', 'valide')
            ->whereNull('l.deleted_at')
            ->groupBy('e.id')
            ->selectRaw('e.id, COALESCE(SUM(l.montant),0) as liquide')
            ->pluck('liquide', 'e.id');

        $paye = DB::table('paiements as p')
            ->join('ordonnancements as o', 'o.id', '=', 'p.ordonnancement_id')
            ->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->where('e.exercice_id', $exerciceId)
            ->where('p.statut', 'valide')
            ->whereNull('p.deleted_at')
            ->groupBy('e.id')
            ->selectRaw('e.id, COALESCE(SUM(CASE WHEN p.montant_xaf > 0 THEN p.montant_xaf ELSE p.montant END),0) as paye')
            ->pluck('paye', 'e.id');

        return $ordonnance->map(function ($r) use ($paye, $liquide) {
            $payeMontant = (float) ($paye[$r->id] ?? 0);
            $ordonnanceMontant = (float) $r->ordonnance;
            $reste = max(0, $ordonnanceMontant - $payeMontant);

            return [
                'numero' => $r->numero,
                'objet' => $r->objet,
                'structure' => $r->structure,
                'beneficiaire' => $r->beneficiaire ?? '—',
                'engage' => (float) $r->engage,
                'liquide' => (float) ($liquide[$r->id] ?? 0),
                'ordonnance' => $ordonnanceMontant,
                'paye' => $payeMontant,
                'reste' => $reste,
            ];
        });
    }
}
