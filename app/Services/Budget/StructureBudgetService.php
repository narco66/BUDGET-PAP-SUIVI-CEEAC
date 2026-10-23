<?php

namespace App\Services\Budget;

use App\Models\Activite;
use App\Models\LigneBudgetaire;
use App\Models\Pilier;
use App\Models\Structure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Couche de restitution unifiée de la « Structure du Budget » (lecture seule, sans nouvelle table).
 * Reconstitue l'arbre Exercice → Département → Direction → Service … (organigramme) et y rattache
 * les lignes budgétaires de l'exercice, avec consolidation remontante des montants (rollup).
 * L'architecture fédérée existante (Structure / RBM / LigneBudgetaire) est conservée.
 */
class StructureBudgetService
{
    /** Champs de structure remontés dans l'arbre. */
    private const COLS = ['id', 'code', 'libelle', 'sigle', 'type', 'parent_id', 'actif', 'ordre'];

    /**
     * Arbre organisationnel budgété d'un exercice : chaque nœud porte ses montants propres et le
     * cumul de tout son sous-arbre (rollup). Retourne les nœuds racines.
     */
    public function arbre(int $exerciceId): array
    {
        $agg = $this->agregatParStructure($exerciceId);
        $structures = Structure::orderBy('ordre')->orderBy('code')->get(self::COLS);
        $parGroupe = $structures->groupBy('parent_id');

        $construire = function (?int $parentId) use (&$construire, $parGroupe, $agg): array {
            return ($parGroupe[$parentId] ?? collect())->map(function (Structure $s) use (&$construire, $agg) {
                $enfants = $construire($s->id);
                $propre = $agg[$s->id] ?? null;

                $ouvert = (float) ($propre->ouvert ?? 0);
                $engage = (float) ($propre->engage ?? 0);
                $paye = (float) ($propre->paye ?? 0);
                $nbPropre = (int) ($propre->nb ?? 0);

                $totalOuvert = $ouvert + array_sum(array_column($enfants, 'total_ouvert'));
                $totalEngage = $engage + array_sum(array_column($enfants, 'total_engage'));
                $totalPaye = $paye + array_sum(array_column($enfants, 'total_paye'));
                $nbTotal = $nbPropre + array_sum(array_column($enfants, 'nb_total'));

                return [
                    'structure' => $s,
                    'propre_ouvert' => $ouvert,
                    'nb_propre' => $nbPropre,
                    'total_ouvert' => $totalOuvert,
                    'total_engage' => $totalEngage,
                    'total_paye' => $totalPaye,
                    'nb_total' => $nbTotal,
                    'enfants' => $enfants,
                ];
            })->values()->all();
        };

        return $construire(null);
    }

    /**
     * Consolidation par type de structure (departement | direction | service) avec rollup :
     * chaque entrée porte le cumul de son sous-arbre. Sert aux tableaux de bord.
     */
    public function totauxParType(int $exerciceId, string $type): Collection
    {
        $plat = collect();
        $aplatir = function (array $noeuds) use (&$aplatir, &$plat): void {
            foreach ($noeuds as $n) {
                $plat->push($n);
                $aplatir($n['enfants']);
            }
        };
        $aplatir($this->arbre($exerciceId));

        return $plat
            ->filter(fn ($n) => $n['structure']->type === $type)
            ->map(fn ($n) => [
                'code' => $n['structure']->code,
                'libelle' => $n['structure']->sigle ?: $n['structure']->libelle,
                'ouvert' => $n['total_ouvert'],
                'engage' => $n['total_engage'],
                'paye' => $n['total_paye'],
                'nb_lignes' => $n['nb_total'],
            ])
            ->filter(fn ($r) => $r['ouvert'] > 0 || $r['nb_lignes'] > 0)
            ->sortByDesc('ouvert')
            ->values();
    }

    /** Lignes budgétaires d'une structure donnée (vue détaillée d'un nœud). */
    public function lignes(int $exerciceId, int $structureId): Collection
    {
        return DB::table('lignes_budgetaires as lb')
            ->leftJoin('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->leftJoin('activites as a', 'lb.activite_id', '=', 'a.id')
            ->leftJoin('sources_financement as sf', 'lb.source_financement_id', '=', 'sf.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')
            ->where('lb.structure_id', $structureId)
            ->orderBy('lb.code')
            ->selectRaw('lb.id, lb.code, lb.intitule, r.code rubrique, a.intitule activite,
                         sf.libelle source, lb.montant_ouvert, lb.montant_engage, lb.montant_paye')
            ->get();
    }

    /**
     * Arbre complet : organigramme institutionnel + chaîne programmatique PAP + synthèse.
     *
     * @return array{institutionnel: array, programmatique: array, synthese: array}
     */
    public function arbreComplet(int $exerciceId): array
    {
        return [
            'institutionnel' => $this->arbre($exerciceId),
            'programmatique' => $this->arbreProgrammatique($exerciceId),
            'synthese' => $this->synthese($exerciceId),
        ];
    }

    /** Chaîne PAP : Pilier → Axe → Produit → Sous-produit → Activité → Ligne(s). */
    public function arbreProgrammatique(int $exerciceId): array
    {
        $lignesParActivite = $this->lignesParActivite($exerciceId);

        $piliers = Pilier::where('exercice_id', $exerciceId)
            ->with([
                'axes.produits.sousProduits.activites' => fn ($q) => $q->with('projetStrategique', 'structure')->orderBy('code'),
            ])
            ->orderBy('ordre')->orderBy('code')->get();

        return $piliers->map(function (Pilier $pilier) use ($lignesParActivite) {
            $axes = $pilier->axes->map(function ($axe) use ($lignesParActivite) {
                $produits = $axe->produits->map(function ($produit) use ($lignesParActivite) {
                    $sousProduits = $produit->sousProduits->map(function ($sp) use ($lignesParActivite) {
                        $activites = $sp->activites->map(function (Activite $act) use ($lignesParActivite) {
                            $lignes = $lignesParActivite->get($act->id, collect());

                            return $this->noeudActivite($act, $lignes);
                        })->values()->all();

                        return $this->rollupNoeud('sous_produit', $sp->code, $sp->intitule, $activites, ['id' => $sp->id]);
                    })->values()->all();

                    return $this->rollupNoeud('produit', $produit->code, $produit->intitule, $sousProduits, ['id' => $produit->id]);
                })->values()->all();

                return $this->rollupNoeud('axe', $axe->code, $axe->intitule, $produits, ['id' => $axe->id]);
            })->values()->all();

            return $this->rollupNoeud('pilier', $pilier->code, $pilier->intitule, $axes, ['id' => $pilier->id]);
        })->values()->all();
    }

    /**
     * Recherche multicritère de lignes budgétaires enrichies (structure, PAP, nomenclature).
     *
     * @param  array<string,mixed>  $filtres
     */
    public function rechercher(int $exerciceId, array $filtres = [], bool $sansPagination = false): LengthAwarePaginator|Collection
    {
        $query = $this->requeteRecherche($exerciceId, $filtres);

        if ($sansPagination) {
            return $query->get();
        }

        return $query->paginate(25)->withQueryString();
    }

    /** Chaîne RBM + nomenclature pour une ligne (fiche détaillée). */
    public function contexteLigne(LigneBudgetaire $ligne): array
    {
        $ligne->load([
            'exercice', 'structure', 'rubrique.paragraphe.article.titre',
            'sourceFinancement.partenaire', 'activite.sousProduit.produit.axe.pilier',
            'activite.projetStrategique', 'imputations.engagement',
        ]);

        $activite = $ligne->activite;
        $chainePap = [];
        if ($activite) {
            $sp = $activite->sousProduit;
            $produit = $sp?->produit ?? $activite->produit;
            $axe = $produit?->axe;
            $pilier = $axe?->pilier;
            $chainePap = array_filter([
                'pilier' => $pilier ? ['code' => $pilier->code, 'intitule' => $pilier->intitule] : null,
                'axe' => $axe ? ['code' => $axe->code, 'intitule' => $axe->intitule] : null,
                'produit' => $produit ? ['code' => $produit->code, 'intitule' => $produit->intitule] : null,
                'sous_produit' => $sp ? ['code' => $sp->code, 'intitule' => $sp->intitule] : null,
                'activite' => ['code' => $activite->code, 'intitule' => $activite->intitule],
                'projet' => $activite->projetStrategique
                    ? ['code' => $activite->projetStrategique->code, 'intitule' => $activite->projetStrategique->intitule]
                    : null,
            ]);
        }

        $rubrique = $ligne->rubrique;
        $chaineNomenclature = [];
        if ($rubrique) {
            $paragraphe = $rubrique->paragraphe;
            $article = $paragraphe?->article;
            $titre = $article?->titre;
            $chaineNomenclature = array_filter([
                'titre' => $titre ? ['code' => $titre->code, 'intitule' => $titre->intitule] : null,
                'article' => $article ? ['code' => $article->code, 'intitule' => $article->intitule] : null,
                'paragraphe' => $paragraphe ? ['code' => $paragraphe->code, 'intitule' => $paragraphe->intitule] : null,
                'rubrique' => ['code' => $rubrique->code, 'intitule' => $rubrique->intitule],
            ]);
        }

        return [
            'ligne' => $ligne,
            'chaine_pap' => $chainePap,
            'chaine_nomenclature' => $chaineNomenclature,
            'imputations' => $ligne->imputations()->with('engagement')->orderByDesc('id')->limit(20)->get(),
        ];
    }

    /** @param  array<string,mixed>  $filtres */
    private function requeteRecherche(int $exerciceId, array $filtres)
    {
        $query = DB::table('lignes_budgetaires as lb')
            ->leftJoin('structures as st', 'lb.structure_id', '=', 'st.id')
            ->leftJoin('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->leftJoin('sources_financement as sf', 'lb.source_financement_id', '=', 'sf.id')
            ->leftJoin('activites as act', 'lb.activite_id', '=', 'act.id')
            ->leftJoin('sous_produits as sp', 'act.sous_produit_id', '=', 'sp.id')
            ->leftJoin('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->leftJoin('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->leftJoin('piliers as pil', 'ax.pilier_id', '=', 'pil.id')
            ->leftJoin('projets as pj', 'act.projet_id', '=', 'pj.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->when($filtres['structure'] ?? null, fn ($q, $v) => $q->where('lb.structure_id', (int) $v))
            ->when($filtres['pilier'] ?? null, fn ($q, $v) => $q->where('pil.id', (int) $v))
            ->when($filtres['rubrique'] ?? null, fn ($q, $v) => $q->where('lb.rubrique_id', (int) $v))
            ->when($filtres['source'] ?? null, fn ($q, $v) => $q->where('lb.source_financement_id', (int) $v))
            ->when($filtres['projet'] ?? null, fn ($q, $v) => $q->where('act.projet_id', (int) $v))
            ->when(isset($filtres['montant_min']) && $filtres['montant_min'] !== '', fn ($q) => $q->where('lb.montant_ouvert', '>=', (float) $filtres['montant_min']))
            ->when(isset($filtres['montant_max']) && $filtres['montant_max'] !== '', fn ($q) => $q->where('lb.montant_ouvert', '<=', (float) $filtres['montant_max']))
            ->when($filtres['q'] ?? null, function ($q, $terme) {
                $terme = trim((string) $terme);
                $q->where(fn ($s) => $s
                    ->where('lb.code', 'like', "%{$terme}%")
                    ->orWhere('lb.intitule', 'like', "%{$terme}%")
                    ->orWhere('r.code', 'like', "%{$terme}%")
                    ->orWhere('act.code', 'like', "%{$terme}%"));
            })
            ->selectRaw("lb.id, lb.code, lb.intitule, lb.montant_ouvert, lb.montant_engage, lb.montant_paye, lb.actif,
                COALESCE(st.sigle, st.libelle, '(non rattachée)') structure_libelle,
                r.code rubrique_code, pil.intitule pilier_intitule, act.intitule activite_intitule,
                pj.intitule projet_intitule, sf.libelle source_libelle")
            ->orderBy('lb.code');

        return $query;
    }

    /** @return Collection<int, Collection<int, object>> */
    private function lignesParActivite(int $exerciceId): Collection
    {
        return DB::table('lignes_budgetaires')
            ->where('exercice_id', $exerciceId)
            ->whereNull('deleted_at')
            ->whereNotNull('activite_id')
            ->selectRaw('id, activite_id, code, intitule, montant_ouvert, montant_engage, montant_paye')
            ->get()
            ->groupBy('activite_id');
    }

    /** @param  Collection<int, object>  $lignes */
    private function noeudActivite(Activite $act, Collection $lignes): array
    {
        $ouvert = $lignes->sum(fn ($l) => (float) $l->montant_ouvert);
        $engage = $lignes->sum(fn ($l) => (float) $l->montant_engage);
        $paye = $lignes->sum(fn ($l) => (float) $l->montant_paye);

        return [
            'type' => 'activite',
            'id' => $act->id,
            'code' => $act->code,
            'intitule' => $act->intitule,
            'structure' => $act->structure?->sigle ?: $act->structure?->libelle,
            'projet' => $act->projetStrategique?->intitule,
            'lignes' => $lignes->values()->all(),
            'nb_lignes' => $lignes->count(),
            'total_ouvert' => $ouvert,
            'total_engage' => $engage,
            'total_paye' => $paye,
            'enfants' => [],
        ];
    }

    /** @param  array<int, array<string, mixed>>  $enfants */
    private function rollupNoeud(string $type, string $code, string $intitule, array $enfants, array $meta = []): array
    {
        return array_merge($meta, [
            'type' => $type,
            'code' => $code,
            'intitule' => $intitule,
            'enfants' => $enfants,
            'nb_lignes' => array_sum(array_column($enfants, 'nb_lignes')),
            'total_ouvert' => array_sum(array_column($enfants, 'total_ouvert')),
            'total_engage' => array_sum(array_column($enfants, 'total_engage')),
            'total_paye' => array_sum(array_column($enfants, 'total_paye')),
        ]);
    }

    /** Synthèse globale : nb structures budgétées, nb lignes, montants. */
    public function synthese(int $exerciceId): array
    {
        $row = DB::table('lignes_budgetaires')
            ->where('exercice_id', $exerciceId)->whereNull('deleted_at')
            ->selectRaw('COUNT(*) nb_lignes, COUNT(DISTINCT structure_id) nb_structures,
                         COALESCE(SUM(montant_ouvert),0) ouvert, COALESCE(SUM(montant_engage),0) engage,
                         COALESCE(SUM(montant_paye),0) paye,
                         SUM(CASE WHEN structure_id IS NULL THEN 1 ELSE 0 END) non_rattachees')
            ->first();

        return [
            'nb_lignes' => (int) ($row->nb_lignes ?? 0),
            'nb_structures' => (int) ($row->nb_structures ?? 0),
            'non_rattachees' => (int) ($row->non_rattachees ?? 0),
            'ouvert' => (float) ($row->ouvert ?? 0),
            'engage' => (float) ($row->engage ?? 0),
            'paye' => (float) ($row->paye ?? 0),
        ];
    }

    /** Agrégat brut des lignes par structure_id (clé = structure_id). */
    private function agregatParStructure(int $exerciceId): Collection
    {
        return DB::table('lignes_budgetaires')
            ->where('exercice_id', $exerciceId)->whereNull('deleted_at')
            ->groupBy('structure_id')
            ->selectRaw('structure_id, COUNT(*) nb, COALESCE(SUM(montant_ouvert),0) ouvert,
                         COALESCE(SUM(montant_engage),0) engage, COALESCE(SUM(montant_paye),0) paye')
            ->get()
            ->keyBy('structure_id');
    }
}
