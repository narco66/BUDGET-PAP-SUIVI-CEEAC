<?php

namespace App\Services\Pap;

use App\Models\Activite;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Calculs de performance PAP (physique déclaratif, financier = exécution réelle budgétaire). */
class PapPerformanceService
{
    /** @var array<int, Collection>|null Activités par exercice, mémorisées pendant {@see memoriser()}. */
    private ?array $memoActivites = null;

    /**
     * Exécute un calcul composite (tableau de pilotage, audit…) en ne chargeant qu'une fois les activités
     * de chaque exercice. Hors de ce périmètre, chaque appel relit la base (aucune donnée périmée).
     *
     * @template TValeur
     * @param  Closure(): TValeur  $calcul
     * @return TValeur
     */
    public function memoriser(Closure $calcul): mixed
    {
        if ($this->memoActivites !== null) {
            return $calcul();
        }

        $this->memoActivites = [];
        try {
            return $calcul();
        } finally {
            $this->memoActivites = null;
        }
    }

    /** Montant payé réel sur la ligne budgétaire liée. */
    public function montantPayeReel(Activite $activite): float
    {
        $activite->loadMissing('ligneBudgetaire');

        if ($activite->ligneBudgetaire) {
            return (float) $activite->ligneBudgetaire->montant_paye;
        }

        if ($activite->ligne_budgetaire_id) {
            return (float) (LigneBudgetaire::find($activite->ligne_budgetaire_id)?->montant_paye ?? 0);
        }

        return 0.0;
    }

    /** Montant engagé réel sur la ligne budgétaire liée. */
    public function montantEngageReel(Activite $activite): float
    {
        $activite->loadMissing('ligneBudgetaire');

        return (float) ($activite->ligneBudgetaire?->montant_engage ?? 0);
    }

    /** Taux financier réel = payé sur ligne budgétaire / budget prévu activité. */
    public function tauxFinancierReel(Activite $activite): float
    {
        $prevu = (float) $activite->montant_total;
        if ($prevu <= 0) {
            return 0.0;
        }

        return round($this->montantPayeReel($activite) / $prevu * 100, 1);
    }

    /**
     * Persiste le taux financier réel sur l'activité (depuis la ligne budgétaire).
     * Retourne false si pas de ligne ou si inchangé.
     */
    public function synchroniserTauxFinancier(Activite $activite): bool
    {
        if (! $activite->ligne_budgetaire_id && $this->montantPayeReel($activite) <= 0) {
            return false;
        }

        $prevu = (float) $activite->montant_total;
        if ($prevu <= 0) {
            return false;
        }

        $taux = round($this->montantPayeReel($activite) / $prevu * 100, 2);
        if ((float) $activite->taux_realisation_financier === $taux) {
            return false;
        }

        $activite->update(['taux_realisation_financier' => $taux]);

        return true;
    }

    /** Synchronise les taux financiers de toutes les activités d'un exercice. */
    public function synchroniserTauxFinanciersExercice(int $exerciceId): int
    {
        $count = 0;
        foreach ($this->activitesExercice($exerciceId) as $activite) {
            if ($this->synchroniserTauxFinancier($activite)) {
                $count++;
            }
        }

        return $count;
    }

    /** Synchronise après mise à jour des compteurs de lignes budgétaires (paiement, etc.). */
    public function synchroniserDepuisLignes(iterable $ligneIds): int
    {
        $ids = collect($ligneIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return 0;
        }

        $count = 0;
        $activiteIds = LigneBudgetaire::whereIn('id', $ids)->whereNotNull('activite_id')->pluck('activite_id');

        Activite::query()
            ->where(fn ($q) => $q->whereIn('ligne_budgetaire_id', $ids)->orWhereIn('id', $activiteIds))
            ->each(function (Activite $activite) use (&$count) {
                if ($this->synchroniserTauxFinancier($activite)) {
                    $count++;
                }
            });

        return $count;
    }

    /** Synthèse globale PAP pour un exercice. */
    public function executionPap(int $exerciceId): array
    {
        $activites = $this->activitesExercice($exerciceId);

        $totalPrevu = (float) $activites->sum('montant_total');
        $totalPaye = (float) $activites->sum(fn (Activite $a) => $this->montantPayeReel($a));
        $tauxPondere = $totalPrevu > 0 ? round($totalPaye / $totalPrevu * 100, 1) : 0.0;
        $tauxMoyen = round((float) $activites->map(fn (Activite $a) => $this->tauxFinancierReel($a))->avg(), 1);

        return [
            'activites' => $activites->count(),
            'taux_physique' => round((float) $activites->avg('taux_realisation_physique'), 1),
            'taux_financier' => $tauxPondere,
            'taux_financier_moyen' => $tauxMoyen,
            'montant_prevu' => round($totalPrevu, 2),
            'montant_paye' => round($totalPaye, 2),
        ];
    }

    /** Consolidation par structure (département / direction / service). */
    public function consolidationParStructure(int $exerciceId): Collection
    {
        return DB::table('activites as a')
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->leftJoin('structures as s', 'a.structure_id', '=', 's.id')
            ->leftJoin('lignes_budgetaires as lb', 'a.ligne_budgetaire_id', '=', 'lb.id')
            ->where('p.exercice_id', $exerciceId)
            ->whereNull('a.deleted_at')
            ->groupBy('s.id', 's.code', 's.libelle', 's.type')
            ->selectRaw('s.id, s.code, s.libelle, s.type,
                COUNT(a.id) activites,
                COALESCE(SUM(a.montant_total),0) budget_prevu,
                COALESCE(SUM(lb.montant_paye),0) budget_paye,
                AVG(a.taux_realisation_physique) taux_physique')
            ->orderBy('s.libelle')
            ->get()
            ->map(function ($r) {
                $prevu = (float) $r->budget_prevu;

                return [
                    'structure_id' => $r->id,
                    'code' => $r->code,
                    'libelle' => $r->libelle ?? '(non rattachée)',
                    'type' => $r->type,
                    'activites' => (int) $r->activites,
                    'budget_prevu' => $prevu,
                    'budget_paye' => (float) $r->budget_paye,
                    'taux_physique' => round((float) $r->taux_physique, 1),
                    'taux_financier' => $prevu > 0 ? round((float) $r->budget_paye / $prevu * 100, 1) : 0.0,
                ];
            });
    }

    /** Performance consolidée par produit. */
    public function performanceProduits(int $exerciceId): Collection
    {
        return DB::table('produits as pr')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->leftJoin('sous_produits as sp', 'sp.produit_id', '=', 'pr.id')
            ->leftJoin('activites as a', function ($j) {
                $j->on('a.sous_produit_id', '=', 'sp.id')->whereNull('a.deleted_at');
            })
            ->leftJoin('lignes_budgetaires as lb', 'a.ligne_budgetaire_id', '=', 'lb.id')
            ->where('p.exercice_id', $exerciceId)
            ->whereNull('pr.deleted_at')
            ->groupBy('pr.id', 'pr.code', 'pr.intitule', 'p.code')
            ->selectRaw('pr.code, pr.intitule, p.code pilier_code,
                COUNT(DISTINCT a.id) activites,
                COALESCE(SUM(a.montant_total),0) budget_prevu,
                COALESCE(SUM(lb.montant_paye),0) budget_paye,
                AVG(a.taux_realisation_physique) taux_physique')
            ->orderBy('pr.code')
            ->get()
            ->map(fn ($r) => [
                'code' => $r->code,
                'intitule' => $r->intitule,
                'pilier_code' => $r->pilier_code,
                'activites' => (int) $r->activites,
                'budget_prevu' => (float) $r->budget_prevu,
                'budget_paye' => (float) $r->budget_paye,
                'taux_physique' => round((float) $r->taux_physique, 1),
                'taux_financier' => (float) $r->budget_prevu > 0
                    ? round((float) $r->budget_paye / (float) $r->budget_prevu * 100, 1) : 0.0,
            ]);
    }

    /** Indice de performance composite (moyenne phys. + fin.). */
    public function indicePerformance(Activite $activite): float
    {
        $phys = (float) ($activite->taux_realisation_physique ?? 0);
        $fin = $this->tauxFinancierReel($activite);

        return round(($phys + $fin) / 2, 1);
    }

    /** Activités en retard (physique < seuil %). */
    public function activitesEnRetard(int $exerciceId, float $seuil = 50): Collection
    {
        return $this->activitesExercice($exerciceId)
            ->filter(fn (Activite $a) => (float) ($a->taux_realisation_physique ?? 0) < $seuil)
            ->values();
    }

    /** Performance détaillée par activité (indices calculés). */
    public function performanceActivites(int $exerciceId): Collection
    {
        return $this->activitesExercice($exerciceId)->map(function (Activite $a) {
            $phys = (float) ($a->taux_realisation_physique ?? 0);
            $fin = $this->tauxFinancierReel($a);
            $indice = round(($phys + $fin) / 2, 1);

            return [
                'activite_id' => $a->id,
                'code' => $a->code,
                'intitule' => $a->intitule,
                'taux_physique' => $phys,
                'taux_financier' => $fin,
                'montant_prevu' => (float) $a->montant_total,
                'montant_paye' => $this->montantPayeReel($a),
                'montant_engage' => $this->montantEngageReel($a),
                'indice_performance' => $indice,
                'en_retard' => $phys < 50,
                'surconsommation' => $fin > 100,
            ];
        });
    }

    /** Écarts physique / financier avec classification métier (seuil en points). */
    public function ecartsPhysiqueFinancier(int $exerciceId, float $seuil = 20.0): Collection
    {
        return $this->performanceActivites($exerciceId)->map(function (array $a) use ($seuil) {
            $phys = $a['taux_physique'];
            $fin = $a['taux_financier'];
            $ecart = round(abs($phys - $fin), 1);
            $type = match (true) {
                $fin > $phys + $seuil => 'sur-financée',
                $phys > $fin + $seuil => 'sous-financée',
                default => 'alignée',
            };

            return array_merge($a, ['ecart' => $ecart, 'type_ecart' => $type]);
        })->sortByDesc('ecart')->values();
    }

    /** Classement des départements par indice de performance composite. */
    public function classementDepartements(int $exerciceId): Collection
    {
        $activites = $this->activitesExercice($exerciceId)->loadMissing('structure.parent');
        $buckets = [];

        foreach ($activites as $activite) {
            $dep = $this->departementParent($activite->structure);
            $key = $dep?->id ?? 0;
            $label = $dep?->libelle ?? '(non rattaché)';

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'structure_id' => $dep?->id,
                    'code' => $dep?->code,
                    'libelle' => $label,
                    'activites' => 0,
                    'physiques' => [],
                    'financiers' => [],
                    'budget_prevu' => 0.0,
                    'budget_paye' => 0.0,
                ];
            }

            $buckets[$key]['activites']++;
            $buckets[$key]['physiques'][] = (float) ($activite->taux_realisation_physique ?? 0);
            $fin = $this->tauxFinancierReel($activite);
            $buckets[$key]['financiers'][] = $fin;
            $buckets[$key]['budget_prevu'] += (float) $activite->montant_total;
            $buckets[$key]['budget_paye'] += (float) ($activite->ligneBudgetaire?->montant_paye ?? 0);
        }

        return collect($buckets)->map(function (array $b) {
            $phys = round((float) collect($b['physiques'])->avg(), 1);
            $fin = round((float) collect($b['financiers'])->avg(), 1);

            return [
                'structure_id' => $b['structure_id'],
                'code' => $b['code'],
                'libelle' => $b['libelle'],
                'activites' => $b['activites'],
                'taux_physique' => $phys,
                'taux_financier' => $fin,
                'indice_performance' => round(($phys + $fin) / 2, 1),
                'budget_prevu' => round($b['budget_prevu'], 2),
                'budget_paye' => round($b['budget_paye'], 2),
            ];
        })
            ->sortByDesc('indice_performance')
            ->values()
            ->map(fn (array $row, int $i) => array_merge($row, ['rang' => $i + 1]));
    }

    private function departementParent(?Structure $structure): ?Structure
    {
        $current = $structure;
        while ($current) {
            if ($current->type === 'departement') {
                return $current;
            }
            $current = $current->parent;
        }

        return null;
    }

    private function activitesExercice(int $exerciceId): Collection
    {
        if ($this->memoActivites !== null) {
            return $this->memoActivites[$exerciceId] ??= $this->chargerActivites($exerciceId);
        }

        return $this->chargerActivites($exerciceId);
    }

    private function chargerActivites(int $exerciceId): Collection
    {
        return Activite::query()
            ->with('ligneBudgetaire')
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->get();
    }
}
