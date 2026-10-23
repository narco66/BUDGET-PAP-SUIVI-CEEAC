<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccessScopeRequest;
use App\Http\Requests\UserRequest;
use App\Models\Exercice;
use App\Models\Fonction;
use App\Models\Poste;
use App\Models\Profil;
use App\Models\Programme;
use App\Models\Structure;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly UserService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $utilisateurs = User::with('structure', 'roles')
            ->when($request->filled('q'), fn ($x) => $x->where(fn ($s) => $s
                ->where('name', 'like', "%{$request->q}%")->orWhere('email', 'like', "%{$request->q}%")
                ->orWhere('matricule', 'like', "%{$request->q}%")))
            ->when($request->filled('statut'), fn ($x) => $x->where('statut', $request->statut))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('utilisateurs.index', compact('utilisateurs'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('utilisateurs.create', $this->options());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $this->service->creer($request->validated(), $request->user(), $request->file('avatar'));

        return redirect()->route('utilisateurs.show', $user)
            ->with('succes', "Utilisateur créé. Mot de passe à changer à la 1ʳᵉ connexion.");
    }

    public function show(User $utilisateur): View
    {
        $this->authorize('view', $utilisateur);
        $utilisateur->load([
            'structure', 'fonction', 'poste', 'roles', 'profils', 'structures', 'accessScopes',
            'historiquesAffectations.structure',
            'delegationsRecues' => fn ($q) => $q->enCours()->with('structure'),
            'interimsAssures' => fn ($q) => $q->enCours()->with('structure', 'titulaire'),
            'loginLogs' => fn ($q) => $q->latest('created_at')->limit(10),
        ]);

        return view('utilisateurs.show', compact('utilisateur'));
    }

    public function edit(User $utilisateur): View
    {
        $this->authorize('update', $utilisateur);
        $utilisateur->load('roles', 'profils', 'structures');

        return view('utilisateurs.edit', array_merge(compact('utilisateur'), $this->options()));
    }

    public function update(UserRequest $request, User $utilisateur): RedirectResponse
    {
        $this->authorize('update', $utilisateur);

        $this->service->mettreAJour($utilisateur, $request->validated(), $request->user(), $request->file('avatar'));

        return redirect()->route('utilisateurs.show', $utilisateur)->with('succes', 'Utilisateur mis à jour.');
    }

    public function destroy(User $utilisateur): RedirectResponse
    {
        $this->authorize('delete', $utilisateur);
        $this->service->changerStatut($utilisateur, 'desactive');

        return redirect()->route('utilisateurs.index')->with('succes', 'Utilisateur désactivé.');
    }

    // --- Actions de cycle de vie ---

    public function changerStatut(Request $request, User $utilisateur): RedirectResponse
    {
        $this->authorize('update', $utilisateur);
        $data = $request->validate(['statut' => 'required|in:actif,suspendu,desactive']);
        abort_if($utilisateur->id === $request->user()->id, 422, 'Vous ne pouvez pas changer votre propre statut.');

        $this->service->changerStatut($utilisateur, $data['statut']);

        return back()->with('succes', 'Statut mis à jour.');
    }

    public function reinitialiserMotDePasse(User $utilisateur): RedirectResponse
    {
        $this->authorize('update', $utilisateur);
        $temporaire = Str::password(12);
        $this->service->reinitialiserMotDePasse($utilisateur, $temporaire);

        return back()->with('succes', "Mot de passe réinitialisé. Mot de passe temporaire : {$temporaire}");
    }

    // --- Périmètre d'accès ---

    public function editScopes(User $utilisateur): View
    {
        $this->authorize('update', $utilisateur);
        $utilisateur->load('accessScopes');

        return view('utilisateurs.scopes', [
            'utilisateur' => $utilisateur,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'type']),
            'programmes' => Programme::orderBy('code')->get(['id', 'code', 'intitule']),
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee']),
        ]);
    }

    public function updateScopes(AccessScopeRequest $request, User $utilisateur): RedirectResponse
    {
        $this->authorize('update', $utilisateur);

        $utilisateur->accessScopes()->delete();
        foreach ($request->validated('scopes', []) as $scope) {
            $utilisateur->accessScopes()->create([
                'type' => $scope['type'],
                'scope_id' => $scope['scope_id'] ?? null,
            ]);
        }

        return redirect()->route('utilisateurs.show', $utilisateur)->with('succes', "Périmètre d'accès mis à jour.");
    }

    private function options(): array
    {
        return [
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'fonctions' => Fonction::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
            'postes' => Poste::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'libelle', 'protege']),
            'profils' => Profil::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
        ];
    }
}
