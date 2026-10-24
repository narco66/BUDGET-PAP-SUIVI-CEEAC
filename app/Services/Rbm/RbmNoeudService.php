<?php

namespace App\Services\Rbm;

use App\Models\Activite;
use App\Models\LigneBudgetaire;
use App\Models\SousProduit;
use App\Models\Structure;
use App\Models\User;
use App\Rbm\Niveaux;
use App\Services\AccessScopeService;
use App\Services\Budget\RbmBudgetSyncService;
use App\Services\Pap\PapReferentielService;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Création / mise à jour des nœuds de la chaîne RBM (partagé Blade + API). */
class RbmNoeudService
{
    public function __construct(
        private readonly RbmReferentielService $referentiel,
        private readonly RbmBudgetSyncService $rbmBudgetSync,
        private readonly PapReferentielService $papReferentiel,
        private readonly AccessScopeService $scopes,
    ) {}

    public function config(string $niveau): array
    {
        abort_unless(Niveaux::existe($niveau), 404);

        return Niveaux::get($niveau);
    }

    public function trouver(string $niveau, int $id): Model
    {
        $config = $this->config($niveau);

        return $config['model']::findOrFail($id);
    }

    public function creer(string $niveau, Request $request): Model
    {
        $this->assertPapModifiable($request);
        $config = $this->config($niveau);
        $data = $this->valider($request, $config, null);
        $parentId = $request->integer('parent');
        if ($config['parent_cle'] && $parentId <= 0) {
            throw new RuntimeException('Le parent est obligatoire pour créer un '.$config['label'].'.');
        }
        if ($config['parent_cle'] && $this->referentiel->codeDoublon($niveau, $parentId, $data['code'] ?? '')) {
            throw new RuntimeException('Un élément avec le même code existe déjà à ce niveau.');
        }
        if ($config['parent_cle'] && $this->referentiel->libelleDoublon($niveau, $parentId, $data['intitule'] ?? '')) {
            throw new RuntimeException('Un élément avec le même intitulé existe déjà à ce niveau.');
        }
        $data = $this->appliquerParent($request, $config, $data);
        $data = $this->normaliser($config, $data);
        $this->assertPerimetreStructure($request->user(), $config, $data);

        $noeud = $config['model']::create($data);
        $this->referentiel->historiser($noeud, 'create', null, $noeud->getAttributes());
        if ($niveau === 'activite' && $noeud instanceof Activite) {
            $this->rbmBudgetSync->synchroniserActivite($noeud);
        }

        return $noeud->fresh();
    }

    public function modifier(string $niveau, int $id, Request $request): Model
    {
        $this->assertPapModifiable($request);
        $config = $this->config($niveau);
        $noeud = $this->trouver($niveau, $id);
        $this->assertNoeudDansPerimetre($request->user(), $niveau, $noeud);

        $data = $this->valider($request, $config, $noeud->id);
        $data = $this->normaliser($config, $data);
        $this->assertPerimetreStructure($request->user(), $config, $data);
        $avant = $noeud->getAttributes();
        $noeud->update($data);
        $this->referentiel->historiser($noeud, 'update', $avant, $noeud->fresh()->getAttributes());
        if ($niveau === 'activite' && $noeud instanceof Activite) {
            $this->rbmBudgetSync->synchroniserActivite($noeud->fresh());
        }

        return $noeud->fresh();
    }

    /** @return array<string, mixed> */
    public function options(string $niveau, User $user, ?int $exerciceId): array
    {
        $config = $this->config($niveau);

        return [
            'niveau' => $niveau,
            'label' => $config['label'],
            'parent_niveau' => $config['parent_niveau'],
            'parent_cle' => $config['parent_cle'],
            'champs' => collect($config['champs'])->map(fn ($meta, $nom) => [
                'nom' => $nom,
                'type' => $meta['type'],
                'requis' => (bool) ($meta['requis'] ?? false),
                'label' => $meta['label'] ?? $nom,
                'max' => $meta['max'] ?? null,
            ])->values()->all(),
            'structures' => $this->scopes
                ->filtrerReferentielStructures(Structure::actif()->orderBy('libelle'), $user)
                ->get(['id', 'code', 'libelle']),
            'users' => User::where('actif', true)->orderBy('name')->limit(200)->get(['id', 'name']),
            'lignes' => LigneBudgetaire::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                ->when(! $this->scopes->estGlobal($user), fn ($q) => $this->scopes->filtrerStructures($q, $user))
                ->orderBy('code')->limit(300)->get(['id', 'code', 'intitule']),
            'statuts_tache' => ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'bloque' => 'Bloqué'],
        ];
    }

    /** @return array<string, mixed> */
    public function serialiser(string $niveau, Model $noeud): array
    {
        $config = $this->config($niveau);
        $valeurs = [];
        foreach (array_keys($config['champs']) as $champ) {
            if ($champ === 'trimestres') {
                $valeurs[$champ] = collect([1, 2, 3, 4])->filter(fn ($t) => (bool) $noeud->{"trim_$t"})->values()->all();
                continue;
            }
            $valeur = $noeud->{$champ} ?? null;
            $type = $config['champs'][$champ]['type'] ?? 'text';
            $valeurs[$champ] = $type === 'decimal' ? Montant::api($valeur ?? 0) : $valeur;
        }

        return [
            'id' => $noeud->id,
            'niveau' => $niveau,
            'label' => $config['label'],
            'parent_id' => $noeud->{$config['parent_cle']} ?? null,
            'code' => $noeud->code ?? null,
            'intitule' => $noeud->intitule ?? null,
            'statut' => $noeud->statut ?? null,
            'champs' => $valeurs,
            'montant_total' => $noeud instanceof Activite ? Montant::api($noeud->montant_total) : null,
        ];
    }

    public function assertNoeudDansPerimetre(User $user, string $niveau, object $noeud): void
    {
        if (! in_array($niveau, ['produit', 'activite'], true)) {
            return;
        }
        if ($this->scopes->dansPerimetre($user, $noeud->structure_id ?? null)) {
            return;
        }

        abort(403, 'Élément hors de votre périmètre institutionnel.');
    }

    private function assertPapModifiable(Request $request): void
    {
        $exerciceId = $request->integer('exercice');
        if ($exerciceId <= 0) {
            return;
        }
        $this->papReferentiel->assertExerciceModifiable($exerciceId);
    }

    /** @param  array<string, mixed>  $config */
    private function valider(Request $request, array $config, ?int $ignoreId): array
    {
        $regles = [];
        foreach ($config['champs'] as $champ => $meta) {
            $regles[$champ] = $this->reglesChamp($champ, $meta, $config, $request, $ignoreId);
        }

        return Validator::make($request->all(), array_filter($regles))->validate();
    }

    /** @param  array<string, mixed>  $meta */
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

    /** @param  array<string, mixed>  $meta */
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

    /** @param  array<string, mixed>  $meta */
    private function reglesSelect(array $meta): array
    {
        return match ($meta['source'] ?? '') {
            'structures' => ['nullable', 'exists:structures,id'],
            'users' => ['nullable', 'exists:users,id'],
            'lignes' => ['nullable', 'exists:lignes_budgetaires,id'],
            'statuts_tache' => ['nullable', Rule::in(['a_faire', 'en_cours', 'termine', 'bloque'])],
            default => ['nullable'],
        };
    }

    /** @param  array<string, mixed>  $config  $data */
    private function appliquerParent(Request $request, array $config, array $data): array
    {
        $data[$config['parent_cle']] = $request->integer('parent');
        if ($config['niveau'] === 'activite') {
            $sp = SousProduit::find($request->integer('parent'));
            $data['produit_id'] = $sp?->produit_id;
        }

        return $data;
    }

    /** @param  array<string, mixed>  $config  $data */
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

    /** @param  array<string, mixed>  $config  $data */
    private function assertPerimetreStructure(?User $user, array $config, array $data): void
    {
        if (! $user || ! in_array($config['niveau'], ['produit', 'activite'], true)) {
            return;
        }
        $structureId = $data['structure_id'] ?? null;
        if ($structureId && ! $this->scopes->dansPerimetre($user, (int) $structureId)) {
            throw new RuntimeException('Structure hors de votre périmètre institutionnel.');
        }
    }
}
