<?php

namespace App\Services\Rbm;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\CadreLogique;
use App\Models\Indicateur;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Programme;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use Illuminate\Support\Facades\DB;

/** Diagnostic d'intégrité du référentiel institutionnel RBM/GAR (phase E7). */
class RbmAuditService
{
    public function __construct(private readonly RbmReferentielService $referentiel)
    {
    }

    /** @return array<string,mixed> */
    public function executer(?int $exerciceId = null): array
    {
        $anomalies = $exerciceId
            ? $this->auditerExercice($exerciceId)
            : $this->auditerTousExercices();

        $critiques = collect($anomalies)->where('niveau', 'critique')->count();

        return [
            'exercice_id' => $exerciceId,
            'genere_le' => now()->toIso8601String(),
            'synthese' => $exerciceId ? $this->synthese($exerciceId) : null,
            'anomalies' => $anomalies,
            'anomalies_critiques' => $critiques,
            'anomalies_total' => count($anomalies),
        ];
    }

    /** @return array<string,int> */
    private function synthese(int $exerciceId): array
    {
        return [
            'piliers' => Pilier::where('exercice_id', $exerciceId)->count(),
            'axes' => Axe::whereHas('pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->count(),
            'activites' => Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->count(),
            'indicateurs' => $this->indicateursExercice($exerciceId)->count(),
            'cadre_logique' => CadreLogique::where('exercice_id', $exerciceId)->exists() ? 1 : 0,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function auditerExercice(int $exerciceId): array
    {
        return array_merge(
            $this->auditerChaine($exerciceId),
            $this->auditerPap($exerciceId),
            $this->auditerIndicateurs($exerciceId),
            $this->auditerIntegrite($exerciceId),
            $this->auditerBlocages($exerciceId),
        );
    }

    /** @return list<array<string,mixed>> */
    private function auditerTousExercices(): array
    {
        $items = [];
        foreach (Pilier::query()->distinct()->pluck('exercice_id') as $exId) {
            foreach ($this->auditerExercice((int) $exId) as $a) {
                $a['exercice_id'] = $exId;
                $items[] = $a;
            }
        }

        return $items;
    }

    /** @return list<array<string,mixed>> */
    private function auditerChaine(int $exerciceId): array
    {
        $items = [];

        if (Pilier::where('exercice_id', $exerciceId)->count() === 0) {
            $items[] = [
                'niveau' => 'critique',
                'code' => 'AUCUN_PILIER',
                'message' => 'Aucun pilier RBM pour cet exercice.',
            ];

            return $items;
        }

        $piliersSansAxe = Pilier::where('exercice_id', $exerciceId)->whereDoesntHave('axes')->count();
        if ($piliersSansAxe) {
            $items[] = [
                'niveau' => 'warning',
                'code' => 'PILIER_SANS_AXE',
                'message' => "{$piliersSansAxe} pilier(s) sans axe stratégique.",
            ];
        }

        $activitesSansSp = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNull('sous_produit_id')->count();
        if ($activitesSansSp) {
            $items[] = [
                'niveau' => 'critique',
                'code' => 'ACTIVITE_SANS_SOUS_PRODUIT',
                'message' => "{$activitesSansSp} activité(s) sans sous-produit (intégrité E2).",
            ];
        }

        $doublonsActivite = DB::table('activites as a')
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->where('p.exercice_id', $exerciceId)
            ->whereNotNull('a.sous_produit_id')
            ->select('a.sous_produit_id', 'a.code')
            ->groupBy('a.sous_produit_id', 'a.code')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        if ($doublonsActivite) {
            $items[] = [
                'niveau' => 'warning',
                'code' => 'DOUBLON_CODE_ACTIVITE',
                'message' => "{$doublonsActivite} doublon(s) de code activité dans un même sous-produit.",
            ];
        }

        return $items;
    }

    /** @return list<array<string,mixed>> */
    private function auditerPap(int $exerciceId): array
    {
        $items = [];
        $pap = Pap::where('exercice_id', $exerciceId)->latest('id')->first();

        if (! $pap) {
            return $items;
        }

        $orphelins = Pilier::where('exercice_id', $exerciceId)->whereNull('pap_id')->count();
        if ($orphelins) {
            $items[] = [
                'niveau' => $pap->statut === 'publie' ? 'critique' : 'warning',
                'code' => 'PILIER_SANS_PAP_ID',
                'message' => "{$orphelins} pilier(s) non rattaché(s) au PAP (pap_id null).",
            ];
        }

        return $items;
    }

    /** @return list<array<string,mixed>> */
    private function auditerIndicateurs(int $exerciceId): array
    {
        $items = [];
        $scope = $this->indicateursExercice($exerciceId);

        $sansCatalogue = (clone $scope)->where(function ($q) {
            $q->whereNull('type_indicateur_id')->orWhereNull('unite_mesure_id');
        })->count();
        if ($sansCatalogue) {
            $items[] = [
                'niveau' => 'critique',
                'code' => 'INDICATEUR_SANS_CATALOGUE',
                'message' => "{$sansCatalogue} indicateur(s) sans type ou unité catalogue (intégrité E3).",
            ];
        }

        $activitesSansInd = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereDoesntHave('indicateurs')->count();
        if ($activitesSansInd) {
            $items[] = [
                'niveau' => 'warning',
                'code' => 'ACTIVITE_SANS_IOV',
                'message' => "{$activitesSansInd} activité(s) sans indicateur IOV.",
            ];
        }

        return $items;
    }

    /** @return list<array<string,mixed>> */
    private function auditerIntegrite(int $exerciceId): array
    {
        $items = [];

        $desync = (int) DB::table('activites as a')
            ->join('lignes_budgetaires as lb', 'lb.id', '=', 'a.ligne_budgetaire_id')
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->where('p.exercice_id', $exerciceId)
            ->where(function ($q) {
                $q->whereNull('lb.activite_id')->orWhereColumn('lb.activite_id', '!=', 'a.id');
            })
            ->count();
        if ($desync) {
            $items[] = [
                'niveau' => 'warning',
                'code' => 'LIAISON_BUDGET_DESYNC',
                'message' => "{$desync} liaison(s) activité↔ligne budgétaire désynchronisée(s).",
            ];
        }

        if (! CadreLogique::where('exercice_id', $exerciceId)->exists()) {
            $items[] = [
                'niveau' => 'info',
                'code' => 'CADRE_LOGIQUE_ABSENT',
                'message' => 'Cadre logique non persisté — ouvrir la vue cadre logique pour synchroniser.',
            ];
        }

        return $items;
    }

    /** Synthèse des nœuds protégés par raisonsBlocage (suppression). */
    /** @return list<array<string,mixed>> */
    private function auditerBlocages(int $exerciceId): array
    {
        $items = [];

        $piliersProgrammes = Pilier::where('exercice_id', $exerciceId)
            ->whereHas('programmes')->count();
        if ($piliersProgrammes) {
            $items[] = [
                'niveau' => 'info',
                'code' => 'PILIER_LIE_PROGRAMME',
                'message' => "{$piliersProgrammes} pilier(s) rattaché(s) à un programme institutionnel (suppression bloquée).",
            ];
        }

        // Mêmes critères que RbmReferentielService::raisonsBlocage() pour une activité (tâches, lignes budgétaires,
        // besoins — un engagement passe toujours par un besoin), comptés en une requête au lieu de trois par activité.
        $activitesProtegees = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->where(fn ($q) => $q->whereHas('taches')
                ->orWhereExists(fn ($s) => $s->from('lignes_budgetaires')->whereColumn('lignes_budgetaires.activite_id', 'activites.id')->whereNull('lignes_budgetaires.deleted_at'))
                ->orWhereExists(fn ($s) => $s->from('expressions_besoin')->whereColumn('expressions_besoin.activite_id', 'activites.id')->whereNull('expressions_besoin.deleted_at')))
            ->count();

        if ($activitesProtegees) {
            $items[] = [
                'niveau' => 'info',
                'code' => 'ACTIVITE_PROTEGEE',
                'message' => "{$activitesProtegees} activité(s) protégée(s) (budget, besoin ou engagement).",
            ];
        }

        $piliersAvecEnfants = Pilier::where('exercice_id', $exerciceId)->has('axes')->count();
        if ($piliersAvecEnfants) {
            $sample = Pilier::where('exercice_id', $exerciceId)->has('axes')->first();
            if ($sample && $this->referentiel->raisonsBlocage($sample)) {
                $items[] = [
                    'niveau' => 'info',
                    'code' => 'NOEUD_AVEC_ENFANTS',
                    'message' => 'Des nœuds RBM ont des sous-éléments (suppression hiérarchique bloquée).',
                ];
            }
        }

        return $items;
    }

    private function indicateursExercice(int $exerciceId)
    {
        $activiteIds = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->pluck('id');
        $tacheIds = Tache::whereHas('activite.sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->pluck('id');
        $pilierIds = Pilier::where('exercice_id', $exerciceId)->pluck('id');
        $axeIds = Axe::whereHas('pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->pluck('id');
        $produitIds = Produit::whereHas('axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->pluck('id');
        $spIds = SousProduit::whereHas('produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->pluck('id');

        if ($activiteIds->isEmpty() && $tacheIds->isEmpty() && $pilierIds->isEmpty()) {
            return Indicateur::query()->whereRaw('0 = 1');
        }

        return Indicateur::query()->where(function ($q) use ($activiteIds, $tacheIds, $pilierIds, $axeIds, $produitIds, $spIds) {
            if ($activiteIds->isNotEmpty()) {
                $q->orWhere(fn ($sub) => $sub->where('rbm_type', Activite::class)->whereIn('rbm_id', $activiteIds));
            }
            if ($tacheIds->isNotEmpty()) {
                $q->orWhere(fn ($sub) => $sub->where('rbm_type', Tache::class)->whereIn('rbm_id', $tacheIds));
            }
            if ($pilierIds->isNotEmpty()) {
                $q->orWhere(fn ($sub) => $sub->where('rbm_type', Pilier::class)->whereIn('rbm_id', $pilierIds));
            }
            if ($axeIds->isNotEmpty()) {
                $q->orWhere(fn ($sub) => $sub->where('rbm_type', Axe::class)->whereIn('rbm_id', $axeIds));
            }
            if ($produitIds->isNotEmpty()) {
                $q->orWhere(fn ($sub) => $sub->where('rbm_type', Produit::class)->whereIn('rbm_id', $produitIds));
            }
            if ($spIds->isNotEmpty()) {
                $q->orWhere(fn ($sub) => $sub->where('rbm_type', SousProduit::class)->whereIn('rbm_id', $spIds));
            }
        });
    }
}
