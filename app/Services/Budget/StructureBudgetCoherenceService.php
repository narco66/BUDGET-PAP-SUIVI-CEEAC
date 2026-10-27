<?php

namespace App\Services\Budget;

use App\Models\Activite;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Contrôles métier et anomalies du module Structure du Budget. */
class StructureBudgetCoherenceService
{
    public function __construct(
        private readonly StructureBudgetService $structureBudget,
    ) {
    }

    /** @return list<array{niveau:string,code:string,message:string}> */
    public function anomalies(int $exerciceId): array
    {
        $items = [];

        $desync = $this->compterLiaisonsDesynchronisees($exerciceId);
        if ($desync > 0) {
            $items[] = [
                'niveau' => 'danger',
                'code' => 'LIAISON_BUDGET_DESYNC',
                'message' => "{$desync} liaison(s) activité ↔ ligne budgétaire désynchronisée(s). Lancez la réconciliation.",
            ];
        }

        $rubriquesBrouillon = (int) DB::table('lignes_budgetaires as lb')
            ->join('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->where('r.statut', '!=', 'valide')
            ->count();
        if ($rubriquesBrouillon > 0) {
            $items[] = [
                'niveau' => 'warning',
                'code' => 'RUBRIQUE_NON_VALIDEE',
                'message' => "{$rubriquesBrouillon} ligne(s) rattachée(s) à une rubrique non validée.",
            ];
        }

        $ecartsPap = $this->ecartsMontantPap($exerciceId);
        if ($ecartsPap->isNotEmpty()) {
            $items[] = [
                'niveau' => 'warning',
                'code' => 'ECART_PAP_BUDGET',
                'message' => $ecartsPap->count().' activité(s) PAP dont le total des lignes dépasse le montant PAP.',
            ];
        }

        $sansStructure = (int) DB::table('lignes_budgetaires')
            ->where('exercice_id', $exerciceId)
            ->whereNull('deleted_at')
            ->whereNull('structure_id')
            ->count();
        if ($sansStructure > 0) {
            $items[] = [
                'niveau' => 'info',
                'code' => 'LIGNES_SANS_STRUCTURE',
                'message' => "{$sansStructure} ligne(s) sans structure institutionnelle.",
            ];
        }

        return $items;
    }

    /** @return Collection<int, object{code:string,intitule:string,montant_pap:float,montant_lignes:float,ecart:float}> */
    public function ecartsMontantPap(int $exerciceId): Collection
    {
        return DB::table('activites as a')
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->where('p.exercice_id', $exerciceId)
            ->where('a.montant_total', '>', 0)
            // Somme des lignes de l'exercice par activité, jointe en une requête (au lieu d'une somme par activité).
            ->leftJoinSub(
                DB::table('lignes_budgetaires')->where('exercice_id', $exerciceId)->whereNull('deleted_at')
                    ->selectRaw('activite_id, SUM(montant_ouvert) somme')->groupBy('activite_id'),
                'lb', 'lb.activite_id', '=', 'a.id')
            ->selectRaw('a.id, a.code, a.intitule, a.montant_total montant_pap, COALESCE(lb.somme, 0) somme_lignes')
            ->get()
            ->map(function ($a) {
                $somme = (float) $a->somme_lignes;
                $ecart = $somme - (float) $a->montant_pap;

                return (object) [
                    'code' => $a->code,
                    'intitule' => $a->intitule,
                    'montant_pap' => (float) $a->montant_pap,
                    'montant_lignes' => $somme,
                    'ecart' => $ecart,
                ];
            })
            ->filter(fn ($r) => $r->ecart > 0.01)
            ->values();
    }

    /** Comparaison budget ouvert N vs exercice précédent (année - 1). */
    public function evolution(int $exerciceId): ?array
    {
        $courant = Exercice::find($exerciceId);
        if (! $courant) {
            return null;
        }

        $precedent = Exercice::where('annee', $courant->annee - 1)->first();
        if (! $precedent) {
            return null;
        }

        $synCourant = $this->structureBudget->synthese($courant->id);
        $synPrecedent = $this->structureBudget->synthese($precedent->id);
        $ouvertCourant = (float) $synCourant['ouvert'];
        $ouvertPrecedent = (float) $synPrecedent['ouvert'];
        $delta = $ouvertCourant - $ouvertPrecedent;
        $pct = $ouvertPrecedent > 0 ? round($delta / $ouvertPrecedent * 100, 1) : null;

        return [
            'annee_courante' => $courant->annee,
            'annee_precedente' => $precedent->annee,
            'ouvert_courant' => $ouvertCourant,
            'ouvert_precedent' => $ouvertPrecedent,
            'engage_courant' => (float) $synCourant['engage'],
            'engage_precedent' => (float) $synPrecedent['engage'],
            'nb_lignes_courant' => (int) $synCourant['nb_lignes'],
            'nb_lignes_precedent' => (int) $synPrecedent['nb_lignes'],
            'delta_ouvert' => $delta,
            'delta_pct' => $pct,
        ];
    }

    /** Répartition des crédits ouverts par sens de nomenclature (recette / dépense). */
    public function repartitionParSens(int $exerciceId): Collection
    {
        return DB::table('lignes_budgetaires as lb')
            ->join('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->join('budget_paragraphes as pa', 'r.paragraphe_id', '=', 'pa.id')
            ->join('budget_articles as ar', 'pa.article_id', '=', 'ar.id')
            ->join('budget_titres as t', 'ar.titre_id', '=', 't.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->groupBy('t.sens')
            ->selectRaw('t.sens, SUM(lb.montant_ouvert) ouvert, COUNT(*) nb')
            ->orderByDesc('ouvert')
            ->get();
    }

    public function compterLiaisonsDesynchronisees(int $exerciceId): int
    {
        return (int) DB::table('activites as a')
            ->join('lignes_budgetaires as lb', 'lb.id', '=', 'a.ligne_budgetaire_id')
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->where('p.exercice_id', $exerciceId)
            // Enveloppe PAP « ACT-{axe} » : partagée par plusieurs activités, sans activité unique — pas une anomalie.
            ->where('lb.code', 'not like', 'ACT-%')
            ->where(function ($q) {
                $q->whereNull('lb.activite_id')->orWhereColumn('lb.activite_id', '!=', 'a.id');
            })
            ->count();
    }

    /** Réconciliation des liaisons pour un exercice (wrapper service sync). */
    public function reconcilier(int $exerciceId, RbmBudgetSyncService $sync): array
    {
        $stats = ['activites' => 0, 'lignes' => 0];

        Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('ligne_budgetaire_id')
            ->each(function (Activite $a) use ($sync, &$stats) {
                $sync->synchroniserActivite($a);
                $stats['activites']++;
            });

        LigneBudgetaire::where('exercice_id', $exerciceId)
            ->whereNotNull('activite_id')
            ->each(function (LigneBudgetaire $l) use ($sync, &$stats) {
                $sync->synchroniserLigne($l);
                $stats['lignes']++;
            });

        return $stats;
    }

    public function rubriqueEstValide(?int $rubriqueId): bool
    {
        if (! $rubriqueId) {
            return false;
        }

        return BudgetRubrique::where('id', $rubriqueId)->where('statut', 'valide')->exists();
    }
}
