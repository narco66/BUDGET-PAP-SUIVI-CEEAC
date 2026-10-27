<?php

namespace App\Services\Rbm;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\CadreLogique;
use App\Models\ChaineResultat;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Indicateur;
use App\Models\LigneBudgetaire;
use App\Models\Pilier;
use App\Models\Programme;
use App\Models\RbmHistorique;
use App\Models\Tache;
use App\Models\User;
use App\Rbm\Niveaux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service métier du référentiel RBM/GAR (Module 2).
 * Centralise les contrôles de cohérence, l'archivage et la construction des vues synthétiques.
 */
class RbmReferentielService
{
    /** Vérifie si un nœud RBM peut être supprimé/archivé (usage PAP, budget, dépense). */
    public function peutSupprimer(Model $noeud): bool
    {
        return empty($this->raisonsBlocage($noeud));
    }

    /** @return list<string> */
    public function raisonsBlocage(Model $noeud): array
    {
        $config = $this->configPourModele($noeud);
        if (! $config) {
            return ['Type de nœud inconnu.'];
        }

        $raisons = [];

        if ($config['relation_enfants'] && $noeud->{$config['relation_enfants']}()->exists()) {
            $raisons[] = 'Des sous-éléments sont encore rattachés.';
        }

        if ($noeud instanceof Pilier && Programme::where('pilier_id', $noeud->id)->exists()) {
            $raisons[] = 'Des programmes institutionnels y sont rattachés.';
        }

        if ($noeud instanceof Activite) {
            if (LigneBudgetaire::where('activite_id', $noeud->id)->exists()) {
                $raisons[] = 'Des lignes budgétaires y sont rattachées.';
            }
            if (ExpressionBesoin::where('activite_id', $noeud->id)->exists()) {
                $raisons[] = 'Des expressions de besoin y sont rattachées.';
            }
            if (Engagement::whereHas('expressionBesoin', fn ($q) => $q->where('activite_id', $noeud->id))->exists()) {
                $raisons[] = 'Des engagements y sont rattachés.';
            }
        }

        return $raisons;
    }

    public function archiver(Model $noeud): void
    {
        DB::transaction(function () use ($noeud) {
            $avant = $noeud->getAttributes();
            $col = method_exists($noeud, 'colonneStatutReferentiel') ? $noeud->colonneStatutReferentiel() : 'statut';
            $noeud->update([$col => 'archive']);
            $this->historiser($noeud, 'archive', $avant, $noeud->fresh()->getAttributes());
        });
    }

    public function restaurer(Model $noeud): void
    {
        DB::transaction(function () use ($noeud) {
            $avant = $noeud->getAttributes();
            $col = method_exists($noeud, 'colonneStatutReferentiel') ? $noeud->colonneStatutReferentiel() : 'statut';
            $noeud->update([$col => 'actif']);
            $this->historiser($noeud, 'restore', $avant, $noeud->fresh()->getAttributes());
        });
    }

    public function historiser(Model $noeud, string $action, ?array $avant, ?array $apres): void
    {
        RbmHistorique::create([
            'historisable_type' => $noeud::class,
            'historisable_id' => $noeud->getKey(),
            'action' => $action,
            'donnees_avant' => $avant,
            'donnees_apres' => $apres,
            'user_id' => auth()->id(),
        ]);
    }

    /** Construit la matrice RBM/GAR aplatie pour un exercice. */
    public function matriceRbm(Exercice $exercice): Collection
    {
        // Comptages par withCount (les tâches et indicateurs n'étaient chargés que pour être comptés).
        $exercice->load([
            'piliers.axes.produits.sousProduits.activites' => fn ($q) => $q->with('structure:id,code,libelle')
                ->withCount(['taches', 'indicateurs']),
        ]);

        $lignes = collect();

        foreach ($exercice->piliers as $pilier) {
            foreach ($pilier->axes as $axe) {
                foreach ($axe->produits as $produit) {
                    foreach ($produit->sousProduits as $sp) {
                        foreach ($sp->activites as $act) {
                            $lignes->push([
                                'pilier' => $pilier->code,
                                'axe' => $axe->code,
                                'produit' => $produit->code,
                                'sous_produit' => $sp->code,
                                'activite' => $act->code,
                                'intitule' => $act->intitule,
                                'montant' => (float) $act->montant_total,
                                'nb_taches' => (int) $act->taches_count,
                                'nb_indicateurs' => (int) $act->indicateurs_count,
                                // Compléments d'affichage (les clés ci-dessus restent inchangées pour les exports et l'API).
                                'activite_id' => $act->id,
                                'pilier_intitule' => $pilier->intitule,
                                'axe_intitule' => $axe->intitule,
                                'produit_intitule' => $produit->intitule,
                                'sous_produit_intitule' => $sp->intitule,
                                'structure' => $act->structure?->code,
                                'structure_libelle' => $act->structure?->libelle,
                                'montant_ceeac' => (float) $act->montant_ceeac,
                                'montant_ptf' => (float) $act->montant_ptf,
                                'taux_physique' => $act->taux_realisation_physique !== null ? (float) $act->taux_realisation_physique : null,
                                'taux_financier' => $act->taux_realisation_financier !== null ? (float) $act->taux_realisation_financier : null,
                            ]);
                        }
                    }
                }
            }
        }

        return $lignes;
    }

    /** Chaîne de résultats synthétique (impact → activités). */
    public function chaineResultats(Exercice $exercice): array
    {
        $exercice->load('piliers.axes.produits.sousProduits.activites');

        return $exercice->piliers->map(fn (Pilier $p) => [
            'niveau' => 'pilier',
            'code' => $p->code,
            'intitule' => $p->intitule,
            'enfants' => $p->axes->map(fn (Axe $a) => [
                'niveau' => 'axe',
                'code' => $a->code,
                'intitule' => $a->intitule,
                'enfants' => $a->produits->flatMap->sousProduits->flatMap->activites->map(fn ($act) => [
                    'niveau' => 'activite',
                    'code' => $act->code,
                    'intitule' => $act->intitule,
                    'montant' => (float) $act->montant_total,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Théorie du changement (GAR) : chaîne causale Impact (pilier) → Effets (axes) → Produits →
     * Activités, enrichie des indicateurs de résultat et des hypothèses/risques critiques.
     * Réutilise intégralement le référentiel RBM existant — aucune nouvelle entité persistée.
     *
     * @return array{piliers: list<array<string, mixed>>, hypotheses: list<array<string, mixed>>}
     */
    public function theorieChangement(Exercice $exercice): array
    {
        $exercice->load([
            'piliers.indicateurs',
            'piliers.axes.indicateurs',
            'piliers.axes.produits.sousProduits.activites',
            'pap.risques',
        ]);

        $piliers = $exercice->piliers->map(fn (Pilier $p) => [
            'code' => $p->code,
            'intitule' => $p->intitule,
            'description' => $p->description,
            'indicateurs' => $p->indicateurs->map->only(['code', 'libelle', 'valeur_cible'])->values()->all(),
            'axes' => $p->axes->map(fn (Axe $a) => [
                'code' => $a->code,
                'intitule' => $a->intitule,
                'indicateurs' => $a->indicateurs->map->only(['code', 'libelle', 'valeur_cible'])->values()->all(),
                'produits' => $a->produits->map(fn ($pr) => [
                    'code' => $pr->code,
                    'intitule' => $pr->intitule,
                    'activites' => $pr->sousProduits->flatMap->activites->map(fn ($act) => [
                        'code' => $act->code,
                        'intitule' => $act->intitule,
                        'montant' => (float) $act->montant_total,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();

        $hypotheses = ($exercice->pap?->risques ?? collect())->map(fn ($r) => [
            'code' => $r->code,
            'libelle' => $r->libelle,
            'probabilite' => $r->probabilite,
            'impact' => $r->impact,
            'mitigation' => $r->mitigation,
        ])->values()->all();

        return ['piliers' => $piliers, 'hypotheses' => $hypotheses];
    }

    /** Cadre logique : objectifs stratégiques + indicateurs par pilier/axe. */
    public function cadreLogique(Exercice $exercice): array
    {
        $exercice->load(['piliers.indicateurs', 'piliers.axes.indicateurs', 'piliers.axes']);
        $commentaires = $this->commentairesAxesCadre($exercice);

        return $exercice->piliers->map(fn (Pilier $p) => [
            'pilier' => $p->only(['code', 'intitule']),
            'objectifs' => $p->axes->map(fn (Axe $a) => [
                'axe' => $a->only(['code', 'intitule']),
                'axe_id' => $a->id,
                'objectif_strategique' => $a->objectif_strategique,
                'commentaire' => $commentaires[(string) $a->id] ?? null,
                'indicateurs' => $a->indicateurs->merge($p->indicateurs)->map->only(['code', 'libelle', 'valeur_cible'])->values(),
            ]),
        ])->values()->all();
    }

    /** Données structurées pour le formulaire d'édition du cadre logique. */
    public function cadreLogiquePourEdition(Exercice $exercice): array
    {
        $exercice->load(['piliers.axes']);
        $document = $this->documentCadreLogique($exercice);
        $commentaires = $document?->elements['commentaires_axes'] ?? [];

        return [
            'document' => $document,
            'piliers' => $exercice->piliers->map(fn (Pilier $p) => [
                'code' => $p->code,
                'intitule' => $p->intitule,
                'axes' => $p->axes->map(fn (Axe $a) => [
                    'id' => $a->id,
                    'code' => $a->code,
                    'intitule' => $a->intitule,
                    'objectif_strategique' => $a->objectif_strategique,
                    'commentaire' => $commentaires[(string) $a->id] ?? '',
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** Enregistre les objectifs stratégiques et commentaires du cadre logique. */
    public function mettreAJourCadreLogique(Exercice $exercice, array $payload, User $user): CadreLogique
    {
        $commentaires = [];

        foreach ($payload['axes'] ?? [] as $axeData) {
            $axe = Axe::query()
                ->whereHas('pilier', fn ($q) => $q->where('exercice_id', $exercice->id))
                ->findOrFail($axeData['id']);

            $axe->update([
                'objectif_strategique' => $axeData['objectif_strategique'] ?? null,
                'updated_by' => $user->id,
            ]);

            $commentaire = trim((string) ($axeData['commentaire'] ?? ''));
            if ($commentaire !== '') {
                $commentaires[(string) $axe->id] = $commentaire;
            }
        }

        $cle = ['exercice_id' => $exercice->id, 'code' => 'CL-'.$exercice->annee];
        $cadre = CadreLogique::firstOrNew($cle);

        if (! $cadre->exists) {
            $cadre->intitule = $payload['intitule'] ?? 'Cadre logique '.$exercice->annee;
        }

        $cadre->fill([
            'intitule' => $payload['intitule'] ?? $cadre->intitule,
            'description' => $payload['description'] ?? $cadre->description,
            'elements' => ['commentaires_axes' => $commentaires],
            'updated_by' => $user->id,
            'statut' => 'actif',
        ]);
        $cadre->save();

        $cadre->update(['matrice' => $this->cadreLogique($exercice)]);

        return $cadre->fresh();
    }

    /** Lignes aplaties pour export Excel/PDF du cadre logique. */
    public function cadreLogiqueLignesExport(Exercice $exercice): Collection
    {
        $lignes = collect();

        foreach ($this->cadreLogique($exercice) as $bloc) {
            foreach ($bloc['objectifs'] as $obj) {
                $iov = collect($obj['indicateurs'])->pluck('code')->filter()->implode(', ');

                $lignes->push([
                    'pilier' => $bloc['pilier']['code'],
                    'pilier_intitule' => $bloc['pilier']['intitule'],
                    'axe' => $obj['axe']['code'],
                    'axe_intitule' => $obj['axe']['intitule'],
                    'objectif_strategique' => $obj['objectif_strategique'] ?? '',
                    'commentaire' => $obj['commentaire'] ?? '',
                    'indicateurs' => $iov,
                ]);
            }
        }

        return $lignes;
    }

    /** @return array<string, string> */
    private function commentairesAxesCadre(Exercice $exercice): array
    {
        return $this->documentCadreLogique($exercice)?->elements['commentaires_axes'] ?? [];
    }

    public function documentCadreLogique(Exercice $exercice): ?CadreLogique
    {
        return CadreLogique::query()
            ->where('exercice_id', $exercice->id)
            ->where('code', 'CL-'.$exercice->annee)
            ->first();
    }

    /** Persiste ou met à jour le cadre logique documenté pour un exercice. */
    public function synchroniserCadreLogique(Exercice $exercice): CadreLogique
    {
        $matrice = $this->cadreLogique($exercice);
        $cle = ['exercice_id' => $exercice->id, 'code' => 'CL-'.$exercice->annee];
        $existant = CadreLogique::query()->where($cle)->first();

        $attributs = [
            'matrice' => $matrice,
            'statut' => 'actif',
        ];

        if (! $existant) {
            $attributs['intitule'] = 'Cadre logique '.$exercice->annee;
        }

        return CadreLogique::updateOrCreate($cle, $attributs);
    }

    /** Persiste la chaîne de résultats documentée pour un exercice. */
    public function synchroniserChaineResultats(Exercice $exercice): ChaineResultat
    {
        return ChaineResultat::updateOrCreate(
            ['exercice_id' => $exercice->id, 'code' => 'CR-'.$exercice->annee],
            [
                'intitule' => 'Chaîne de résultats '.$exercice->annee,
                'elements' => $this->chaineResultats($exercice),
                'statut' => 'actif',
            ]
        );
    }

    /** Vérifie l'unicité du code dans le même parent (activité, tâche, etc.). */
    public function codeDoublon(string $niveau, int $parentId, string $code, ?int $ignoreId = null): bool
    {
        if ($code === '' || ! Niveaux::existe($niveau)) {
            return false;
        }

        $config = Niveaux::get($niveau);
        $query = $config['model']::query()
            ->where($config['parent_cle'], $parentId)
            ->where('code', $code);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /** Vérifie l'unicité du libellé/intitulé dans le même parent. */
    public function libelleDoublon(string $niveau, int $parentId, string $libelle, ?int $ignoreId = null): bool
    {
        $config = Niveaux::get($niveau);
        $colLibelle = in_array($niveau, ['pilier', 'axe', 'produit', 'sous_produit', 'activite', 'tache'], true) ? 'intitule' : 'libelle';

        $query = $config['model']::query()
            ->where($config['parent_cle'], $parentId)
            ->where($colLibelle, $libelle);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /** Historique paginé des modifications sur un exercice. */
    public function historiquesExercice(Exercice $exercice, ?string $action = null)
    {
        $exercice->load('piliers.axes.produits.sousProduits.activites.taches');
        $filtres = $this->idsParType($exercice);

        $query = RbmHistorique::query()->with('utilisateur')->latest();

        $query->where(function ($q) use ($filtres) {
            foreach ($filtres as $type => $ids) {
                if ($ids->isNotEmpty()) {
                    $q->orWhere(fn ($sub) => $sub->where('historisable_type', $type)->whereIn('historisable_id', $ids));
                }
            }
        });

        if ($action) {
            $query->where('action', $action);
        }

        return $query->paginate(25)->withQueryString();
    }

    /** Catalogue aplatit d'un niveau RBM pour export PDF. */
    public function catalogueNiveau(Exercice $exercice, string $niveau): Collection
    {
        $exercice->load('piliers.axes.produits.sousProduits.activites.taches');
        $items = collect();

        foreach ($exercice->piliers as $pilier) {
            if ($niveau === 'pilier') {
                $items->push($this->formatCatalogue($pilier, 'pilier', $pilier->code));

                continue;
            }
            foreach ($pilier->axes as $axe) {
                if ($niveau === 'axe') {
                    $items->push($this->formatCatalogue($axe, 'axe', $pilier->code));

                    continue;
                }
                foreach ($axe->produits as $produit) {
                    if ($niveau === 'produit') {
                        $items->push($this->formatCatalogue($produit, 'produit', $pilier->code.' / '.$axe->code));

                        continue;
                    }
                    foreach ($produit->sousProduits as $sp) {
                        if ($niveau === 'sous_produit') {
                            $items->push($this->formatCatalogue($sp, 'sous_produit', $produit->code));

                            continue;
                        }
                        foreach ($sp->activites as $act) {
                            if ($niveau === 'activite') {
                                $items->push($this->formatCatalogue($act, 'activite', $sp->code));

                                continue;
                            }
                            foreach ($act->taches as $tache) {
                                if ($niveau === 'tache') {
                                    $items->push([
                                        'code' => $tache->code,
                                        'intitule' => $tache->intitule,
                                        'contexte' => $act->code,
                                        'statut' => $tache->statut_referentiel ?? 'actif',
                                        'ordre' => $tache->ordre,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $items;
    }

    /** Tous les indicateurs rattachés à la chaîne d'un exercice. */
    public function catalogueIndicateurs(Exercice $exercice): Collection
    {
        $exercice->load([
            'piliers.indicateurs',
            'piliers.axes.indicateurs',
            'piliers.axes.produits.indicateurs',
            'piliers.axes.produits.sousProduits.indicateurs',
            'piliers.axes.produits.sousProduits.activites.indicateurs',
        ]);

        $items = collect();
        foreach ($exercice->piliers as $pilier) {
            foreach ($pilier->indicateurs as $ind) {
                $items->push($this->formatIndicateur($ind, $pilier->code));
            }
            foreach ($pilier->axes as $axe) {
                foreach ($axe->indicateurs as $ind) {
                    $items->push($this->formatIndicateur($ind, $pilier->code.' / '.$axe->code));
                }
                foreach ($axe->produits as $produit) {
                    foreach ($produit->indicateurs as $ind) {
                        $items->push($this->formatIndicateur($ind, $produit->code));
                    }
                    foreach ($produit->sousProduits as $sp) {
                        foreach ($sp->indicateurs as $ind) {
                            $items->push($this->formatIndicateur($ind, $sp->code));
                        }
                        foreach ($sp->activites as $act) {
                            foreach ($act->indicateurs as $ind) {
                                $items->push($this->formatIndicateur($ind, $act->code));
                            }
                        }
                    }
                }
            }
        }

        return $items->unique('code')->values();
    }

    /** @return array<class-string,Collection<int,int>> */
    private function idsParType(Exercice $exercice): array
    {
        $piliers = $exercice->piliers;
        $axes = $piliers->flatMap->axes;
        $produits = $axes->flatMap->produits;
        $sps = $produits->flatMap->sousProduits;
        $activites = $sps->flatMap->activites;
        $taches = $activites->flatMap->taches;

        return [
            Pilier::class => $piliers->pluck('id'),
            Axe::class => $axes->pluck('id'),
            Produit::class => $produits->pluck('id'),
            SousProduit::class => $sps->pluck('id'),
            Activite::class => $activites->pluck('id'),
            Tache::class => $taches->pluck('id'),
            Indicateur::class => Indicateur::whereIn('rbm_id', $piliers->pluck('id'))->where('rbm_type', Pilier::class)->pluck('id')
                ->merge(Indicateur::whereIn('rbm_id', $axes->pluck('id'))->where('rbm_type', Axe::class)->pluck('id'))
                ->merge(Indicateur::whereIn('rbm_id', $activites->pluck('id'))->where('rbm_type', Activite::class)->pluck('id')),
        ];
    }

    private function formatCatalogue(Model $noeud, string $niveau, string $contexte): array
    {
        $statut = method_exists($noeud, 'colonneStatutReferentiel')
            ? ($noeud->{$noeud->colonneStatutReferentiel()} ?? 'actif')
            : ($noeud->statut ?? 'actif');

        return [
            'code' => $noeud->code,
            'intitule' => $noeud->intitule,
            'contexte' => $contexte,
            'statut' => $statut,
            'ordre' => $noeud->ordre ?? 0,
        ];
    }

    private function formatIndicateur(Indicateur $ind, string $contexte): array
    {
        return [
            'code' => $ind->code,
            'libelle' => $ind->libelle,
            'type' => $ind->type,
            'unite' => $ind->unite,
            'periodicite' => $ind->periodicite,
            'cible' => $ind->valeur_cible,
            'contexte' => $contexte,
        ];
    }

    private function configPourModele(Model $noeud): ?array
    {
        foreach (Niveaux::tout() as $niveau => $config) {
            if ($noeud instanceof $config['model']) {
                return $config + ['niveau' => $niveau];
            }
        }

        return null;
    }
}
