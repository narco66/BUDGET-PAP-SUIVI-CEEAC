<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Models\User;
use App\Rbm\Niveaux;
use App\Services\Pap\PapReferentielService;
use App\Services\Rbm\RbmNoeudService;
use App\Services\Rbm\RbmReferentielService;
use App\Services\AccessScopeService;
use App\Support\RbmAuthorization;
use App\Support\RbmStatutFiltre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class RbmController extends Controller
{
    public function __construct(
        private readonly RbmReferentielService $referentiel,
        private readonly RbmNoeudService $noeuds,
        private readonly PapReferentielService $papReferentiel,
        private readonly AccessScopeService $scopes,
    ) {
    }

    /** Arbre RBM complet d'un exercice. */
    public function arbre(Request $request, Exercice $exercice): View
    {
        abort_unless($request->user()->can('rbm.voir'), 403);

        $exercice->load([
            'piliers.axes.produits.sousProduits.activites.taches',
        ]);
        $this->filtrerArbreParPerimetre($exercice, $request->user());

        $statutFiltre = RbmStatutFiltre::normaliser($request->query('statut'));
        $exercices = Exercice::orderByDesc('annee')->get(['id', 'annee', 'libelle', 'statut']);

        return view('rbm.arbre', compact('exercice', 'exercices', 'statutFiltre'));
    }

    public function create(Request $request, string $niveau): View
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'creer'), 403);
        $config = $this->config($niveau);
        $parentId = $request->integer('parent');
        $exerciceId = $request->integer('exercice');

        return view('rbm.form', [
            'config' => $config,
            'niveau' => $niveau,
            'noeud' => null,
            'parentId' => $parentId,
            'exerciceId' => $exerciceId,
            'options' => $this->noeuds->options($niveau, $request->user(), $exerciceId ?: null),
        ]);
    }

    public function store(Request $request, string $niveau): RedirectResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'creer'), 403);
        $config = $this->config($niveau);
        try {
            $this->noeuds->creer($niveau, $request);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()
            ->route('rbm.arbre', $request->integer('exercice'))
            ->with('succes', $config['label'].' créé(e).');
    }

    public function edit(Request $request, string $niveau, int $id): View
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'modifier'), 403);
        $config = $this->config($niveau);
        $noeud = $this->noeuds->trouver($niveau, $id);
        $this->noeuds->assertNoeudDansPerimetre($request->user(), $niveau, $noeud);

        return view('rbm.form', [
            'config' => $config,
            'niveau' => $niveau,
            'noeud' => $noeud,
            'parentId' => $noeud->{$config['parent_cle']},
            'exerciceId' => $request->integer('exercice'),
            'options' => $this->noeuds->options($niveau, $request->user(), $request->integer('exercice') ?: null),
        ]);
    }

    public function update(Request $request, string $niveau, int $id): RedirectResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'modifier'), 403);
        $config = $this->config($niveau);
        try {
            $this->noeuds->modifier($niveau, $id, $request);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()
            ->route('rbm.arbre', $request->integer('exercice'))
            ->with('succes', $config['label'].' mis(e) à jour.');
    }

    public function destroy(Request $request, string $niveau, int $id): RedirectResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'supprimer'), 403);
        if ($request->integer('exercice') > 0) {
            try {
                $this->papReferentiel->assertExerciceModifiable($request->integer('exercice'));
            } catch (RuntimeException $e) {
                return back()->with('erreur', $e->getMessage());
            }
        }
        $config = $this->config($niveau);
        $noeud = $config['model']::findOrFail($id);
        $this->noeuds->assertNoeudDansPerimetre($request->user(), $niveau, $noeud);

        if ($config['relation_enfants'] && $noeud->{$config['relation_enfants']}()->exists()) {
            return back()->with('erreur', 'Impossible : cet élément a des sous-éléments rattachés.');
        }

        $raisons = $this->referentiel->raisonsBlocage($noeud);
        if ($raisons) {
            return back()->with('erreur', 'Suppression impossible : '.implode(' ', $raisons));
        }

        $avant = $noeud->getAttributes();
        $noeud->delete();
        $this->referentiel->historiser($noeud, 'delete', $avant, null);

        return redirect()
            ->route('rbm.arbre', $request->integer('exercice'))
            ->with('succes', $config['label'].' supprimé(e).');
    }

    public function archiver(Request $request, string $niveau, int $id): RedirectResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'archiver'), 403);
        $config = $this->config($niveau);
        $noeud = $config['model']::findOrFail($id);
        $this->referentiel->archiver($noeud);

        return back()->with('succes', $config['label'].' archivé(e).');
    }

    public function restaurer(Request $request, string $niveau, int $id): RedirectResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'restaurer'), 403);
        $config = $this->config($niveau);
        $noeud = $config['model']::withTrashed()->findOrFail($id);
        $this->referentiel->restaurer($noeud);

        return back()->with('succes', $config['label'].' restauré(e).');
    }

    private function config(string $niveau): array
    {
        abort_unless(Niveaux::existe($niveau), 404);

        return Niveaux::get($niveau);
    }

    private function filtrerArbreParPerimetre(Exercice $exercice, User $user): void
    {
        if ($this->scopes->estGlobal($user)) {
            return;
        }

        $ids = $this->scopes->structuresAutorisees($user);

        foreach ($exercice->piliers as $pilier) {
            foreach ($pilier->axes as $axe) {
                $axe->setRelation(
                    'produits',
                    $axe->produits->filter(fn ($produit) => ! $produit->structure_id || $ids->contains($produit->structure_id))
                );

                foreach ($axe->produits as $produit) {
                    foreach ($produit->sousProduits as $sousProduit) {
                        $sousProduit->setRelation(
                            'activites',
                            $sousProduit->activites->filter(fn ($activite) => ! $activite->structure_id || $ids->contains($activite->structure_id))
                        );
                    }
                }
            }
        }
    }
}
