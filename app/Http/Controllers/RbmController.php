<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\User;
use App\Rbm\Niveaux;
use App\Services\Rbm\RbmReferentielService;
use App\Services\AccessScopeService;
use App\Services\Budget\RbmBudgetSyncService;
use App\Services\Pap\PapReferentielService;
use App\Support\RbmAuthorization;
use App\Support\RbmStatutFiltre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class RbmController extends Controller
{
    public function __construct(
        private readonly RbmReferentielService $referentiel,
        private readonly RbmBudgetSyncService $rbmBudgetSync,
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
            'options' => $this->options($exerciceId),
        ]);
    }

    public function store(Request $request, string $niveau): RedirectResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'creer'), 403);
        if ($redirect = $this->verrouPap($request)) {
            return $redirect;
        }
        $config = $this->config($niveau);

        $data = $this->valider($request, $config, null);
        $parentId = $request->integer('parent');
        if ($config['parent_cle'] && $parentId <= 0) {
            return back()->withInput()->with('erreur', 'Le parent est obligatoire pour créer un '.$config['label'].'.');
        }
        if ($config['parent_cle'] && $this->referentiel->codeDoublon($niveau, $parentId, $data['code'] ?? '')) {
            return back()->withInput()->with('erreur', 'Un élément avec le même code existe déjà à ce niveau.');
        }
        if ($config['parent_cle'] && $this->referentiel->libelleDoublon($niveau, $parentId, $data['intitule'] ?? '')) {
            return back()->withInput()->with('erreur', 'Un élément avec le même intitulé existe déjà à ce niveau.');
        }
        $data = $this->appliquerParent($request, $config, $data);
        $data = $this->normaliser($config, $data);
        if ($redirect = $this->validerPerimetreStructure($request, $config, $data)) {
            return $redirect;
        }

        $noeud = $config['model']::create($data);
        $this->referentiel->historiser($noeud, 'create', null, $noeud->getAttributes());
        if ($niveau === 'activite' && $noeud instanceof \App\Models\Activite) {
            $this->rbmBudgetSync->synchroniserActivite($noeud);
        }

        return redirect()
            ->route('rbm.arbre', $request->integer('exercice'))
            ->with('succes', $config['label'].' créé(e).');
    }

    public function edit(Request $request, string $niveau, int $id): View
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'modifier'), 403);
        $config = $this->config($niveau);
        $noeud = $config['model']::findOrFail($id);
        $this->assertNoeudDansPerimetre($request->user(), $niveau, $noeud);

        return view('rbm.form', [
            'config' => $config,
            'niveau' => $niveau,
            'noeud' => $noeud,
            'parentId' => $noeud->{$config['parent_cle']},
            'exerciceId' => $request->integer('exercice'),
            'options' => $this->options($request->integer('exercice')),
        ]);
    }

    public function update(Request $request, string $niveau, int $id): RedirectResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'modifier'), 403);
        if ($redirect = $this->verrouPap($request)) {
            return $redirect;
        }
        $config = $this->config($niveau);
        $noeud = $config['model']::findOrFail($id);
        $this->assertNoeudDansPerimetre($request->user(), $niveau, $noeud);

        $data = $this->valider($request, $config, $noeud->id);
        $data = $this->normaliser($config, $data);
        if ($redirect = $this->validerPerimetreStructure($request, $config, $data)) {
            return $redirect;
        }
        $avant = $noeud->getAttributes();
        $noeud->update($data);
        $this->referentiel->historiser($noeud, 'update', $avant, $noeud->fresh()->getAttributes());
        if ($niveau === 'activite' && $noeud instanceof \App\Models\Activite) {
            $this->rbmBudgetSync->synchroniserActivite($noeud->fresh());
        }

        return redirect()
            ->route('rbm.arbre', $request->integer('exercice'))
            ->with('succes', $config['label'].' mis(e) à jour.');
    }

    public function destroy(Request $request, string $niveau, int $id): RedirectResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'supprimer'), 403);
        if ($redirect = $this->verrouPap($request)) {
            return $redirect;
        }
        $config = $this->config($niveau);
        $noeud = $config['model']::findOrFail($id);
        $this->assertNoeudDansPerimetre($request->user(), $niveau, $noeud);

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

    // --- Helpers ---

    /** Bloque les mutations RBM si le PAP de l'exercice est verrouillé. */
    private function verrouPap(Request $request): ?RedirectResponse
    {
        $exerciceId = $request->integer('exercice');
        if ($exerciceId <= 0) {
            return null;
        }

        try {
            $this->papReferentiel->assertExerciceModifiable($exerciceId);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return null;
    }

    private function config(string $niveau): array
    {
        abort_unless(Niveaux::existe($niveau), 404);

        return Niveaux::get($niveau);
    }

    /** Construit et applique les règles de validation à partir du registre. */
    private function valider(Request $request, array $config, ?int $ignoreId): array
    {
        $regles = [];
        foreach ($config['champs'] as $champ => $meta) {
            $regles[$champ] = $this->reglesChamp($champ, $meta, $config, $request, $ignoreId);
        }

        return Validator::make($request->all(), array_filter($regles))->validate();
    }

    private function reglesChamp(string $champ, array $meta, array $config, Request $request, ?int $ignoreId): array
    {
        return match ($meta['type']) {
            'text' => $this->reglesTexte($champ, $meta, $config, $request, $ignoreId),
            'textarea' => ['nullable', 'string'],
            'number' => ['nullable', 'integer', 'min:0'],
            'decimal' => ['nullable', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
            'select' => $this->reglesSelect($meta),
            'trimestres' => ['nullable', 'array'],
            default => ['nullable'],
        };
    }

    private function reglesTexte(string $champ, array $meta, array $config, Request $request, ?int $ignoreId): array
    {
        $regles = [($meta['requis'] ?? false) ? 'required' : 'nullable', 'string', 'max:'.($meta['max'] ?? 255)];

        if (! empty($meta['code_unique'])) {
            $rule = Rule::unique($config['table'], $champ)
                ->where($config['parent_cle'], $request->integer('parent'));
            if ($ignoreId) {
                $rule->ignore($ignoreId);
            }
            $regles[] = $rule;
        }

        return $regles;
    }

    private function reglesSelect(array $meta): array
    {
        return match ($meta['source']) {
            'structures' => ['nullable', 'exists:structures,id'],
            'users' => ['nullable', 'exists:users,id'],
            'lignes' => ['nullable', 'exists:lignes_budgetaires,id'],
            'statuts_tache' => ['nullable', Rule::in(['a_faire', 'en_cours', 'termine', 'bloque'])],
            default => ['nullable'],
        };
    }

    /** Rattache l'élément à son parent (sauf à la création où le parent vient de la requête). */
    private function appliquerParent(Request $request, array $config, array $data): array
    {
        $data[$config['parent_cle']] = $request->integer('parent');

        // Une activité est rattachée au sous-produit ; on renseigne aussi produit_id (dénormalisation utile).
        if ($config['niveau'] === 'activite') {
            $sp = \App\Models\SousProduit::find($request->integer('parent'));
            $data['produit_id'] = $sp?->produit_id;
        }

        return $data;
    }

    /** Transforme les champs spéciaux (trimestres → booléens, montant_total = somme). */
    private function normaliser(array $config, array $data): array
    {
        if (array_key_exists('trimestres', $config['champs'])) {
            $coches = (array) ($data['trimestres'] ?? []);
            foreach ([1, 2, 3, 4] as $t) {
                $data["trim_$t"] = in_array((string) $t, array_map('strval', $coches), true);
            }
            unset($data['trimestres']);
        }

        if ($config['niveau'] === 'activite') {
            $data['montant_total'] = (float) ($data['montant_ceeac'] ?? 0) + (float) ($data['montant_ptf'] ?? 0);
        }

        return $data;
    }

    /** Options des listes déroulantes. */
    private function options(?int $exerciceId): array
    {
        $user = auth()->user();

        return [
            'structures' => $this->scopes
                ->filtrerReferentielStructures(Structure::actif()->orderBy('libelle'), $user)
                ->get(['id', 'code', 'libelle']),
            'users' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
            'lignes' => LigneBudgetaire::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                ->when(! $this->scopes->estGlobal($user), fn ($q) => $this->scopes->filtrerStructures($q, $user))
                ->orderBy('code')->get(['id', 'code', 'intitule']),
            'statuts_tache' => ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'bloque' => 'Bloqué'],
        ];
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

    private function assertNoeudDansPerimetre(User $user, string $niveau, object $noeud): void
    {
        if (! in_array($niveau, ['produit', 'activite'], true)) {
            return;
        }

        if ($this->scopes->dansPerimetre($user, $noeud->structure_id ?? null)) {
            return;
        }

        abort(403, 'Élément hors de votre périmètre institutionnel.');
    }

    /** @param  array<string, mixed>  $data */
    private function validerPerimetreStructure(Request $request, array $config, array $data): ?RedirectResponse
    {
        if (! in_array($config['niveau'], ['produit', 'activite'], true)) {
            return null;
        }

        $structureId = $data['structure_id'] ?? null;
        if ($structureId && ! $this->scopes->dansPerimetre($request->user(), (int) $structureId)) {
            return back()->withInput()->with('erreur', 'Structure hors de votre périmètre institutionnel.');
        }

        return null;
    }
}
