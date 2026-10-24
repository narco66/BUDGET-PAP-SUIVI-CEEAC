<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessScopeRequest;
use App\Http\Requests\UserRequest;
use App\Models\Fonction;
use App\Models\Poste;
use App\Models\Profil;
use App\Models\Structure;
use App\Models\User;
use App\Services\Api\IdempotenceActionService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/** Comptes et habilitations — session SPA. Rôles / profils / journal restent Blade. */
class UtilisateurApiController extends Controller
{
    /** @var array<string, string> */
    public const STATUTS = [
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
        'desactive' => 'Désactivé',
    ];

    public function __construct(
        private readonly UserService $service,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $user = $request->user();

        $liste = User::query()
            ->with('structure:id,code,libelle,sigle', 'roles:id,name,libelle')
            ->when($request->filled('q'), fn ($x) => $x->where(fn ($s) => $s
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')
                ->orWhere('matricule', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('statut'), fn ($x) => $x->where('statut', $request->string('statut')))
            ->orderBy('name')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'statuts' => self::STATUTS,
            'actions' => [
                'creer' => $user?->can('utilisateur.creer') ?? false,
            ],
            'liens_blade' => [
                'roles' => $user?->can('role.voir') ? $this->href('roles.index') : null,
                'profils' => $user?->can('profil.voir') ? $this->href('profils.index') : null,
                'permissions' => $user?->can('role.voir') ? $this->href('permissions.index') : null,
                'journal' => $user?->can('utilisateur.consulter_journal') ? $this->href('logs.connexions') : null,
            ],
            'data' => collect($liste->items())->map(fn (User $u) => $this->serialiser($u, $user, false))->values(),
            'meta' => [
                'total' => $liste->total(),
                'page' => $liste->currentPage(),
                'per_page' => $liste->perPage(),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return response()->json($this->optionsFormulaire());
    }

    public function show(Request $request, User $utilisateur): JsonResponse
    {
        $this->authorize('view', $utilisateur);
        $utilisateur->load([
            'structure:id,code,libelle,sigle',
            'fonction:id,libelle',
            'poste:id,libelle',
            'roles:id,name,libelle,protege',
            'profils:id,libelle',
            'structures:id,code,libelle',
            'accessScopes',
            'loginLogs' => fn ($q) => $q->latest('created_at')->limit(10),
        ]);

        return response()->json([
            'utilisateur' => $this->serialiser($utilisateur, $request->user(), true),
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validated();
        $cle = 'utilisateur.creer.'.($data['email'] ?? 'x');

        $resultat = $this->idempotence->executer($request, $cle, function () use ($request, $data) {
            $user = $this->service->creer($data, $request->user());

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Utilisateur créé. Mot de passe à changer à la 1ʳᵉ connexion.',
                    'utilisateur' => $this->serialiser($user->fresh(['structure', 'roles', 'profils']), $request->user(), true),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(UserRequest $request, User $utilisateur): JsonResponse
    {
        $this->authorize('update', $utilisateur);
        $this->service->mettreAJour($utilisateur, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Utilisateur mis à jour.',
            'utilisateur' => $this->serialiser($utilisateur->fresh(['structure', 'roles', 'profils', 'structures', 'accessScopes']), $request->user(), true),
        ]);
    }

    public function destroy(Request $request, User $utilisateur): JsonResponse
    {
        $this->authorize('delete', $utilisateur);
        $this->service->changerStatut($utilisateur, 'desactive');

        return response()->json(['message' => 'Utilisateur désactivé.']);
    }

    public function changerStatut(Request $request, User $utilisateur): JsonResponse
    {
        $this->authorize('update', $utilisateur);
        if ($utilisateur->id === $request->user()?->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas changer votre propre statut.',
                'code' => 'transition_invalide',
            ], 422);
        }
        $data = $request->validate(['statut' => 'required|in:actif,suspendu,desactive']);
        $this->service->changerStatut($utilisateur, $data['statut']);

        return response()->json([
            'message' => 'Statut mis à jour.',
            'utilisateur' => $this->serialiser($utilisateur->fresh(['structure', 'roles']), $request->user(), true),
        ]);
    }

    public function reinitialiser(Request $request, User $utilisateur): JsonResponse
    {
        $this->authorize('update', $utilisateur);
        $temporaire = Str::password(12);
        $this->service->reinitialiserMotDePasse($utilisateur, $temporaire);

        return response()->json([
            'message' => 'Mot de passe réinitialisé.',
            'mot_de_passe_temporaire' => $temporaire,
            'utilisateur' => $this->serialiser($utilisateur->fresh(['structure', 'roles']), $request->user(), false),
        ]);
    }

    public function perimetres(Request $request, User $utilisateur): JsonResponse
    {
        $this->authorize('view', $utilisateur);

        return response()->json([
            'scopes' => $utilisateur->accessScopes()->get(['id', 'type', 'scope_id']),
        ]);
    }

    public function enregistrerPerimetres(AccessScopeRequest $request, User $utilisateur): JsonResponse
    {
        $this->authorize('update', $utilisateur);
        $utilisateur->accessScopes()->delete();
        foreach ($request->validated('scopes', []) as $scope) {
            $utilisateur->accessScopes()->create([
                'type' => $scope['type'],
                'scope_id' => $scope['scope_id'] ?? null,
            ]);
        }

        return response()->json([
            'message' => "Périmètre d'accès mis à jour.",
            'utilisateur' => $this->serialiser($utilisateur->fresh(['structure', 'roles', 'accessScopes']), $request->user(), true),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialiser(User $u, mixed $acteur, bool $detail): array
    {
        $base = [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'matricule' => $u->matricule,
            'statut' => $u->statut,
            'actif' => (bool) $u->actif,
            'structure' => $u->structure ? [
                'id' => $u->structure->id,
                'code' => $u->structure->code,
                'libelle' => $u->structure->libelle,
                'sigle' => $u->structure->sigle ?? null,
            ] : null,
            'roles' => $u->roles->map(fn ($r) => [
                'name' => $r->name,
                'libelle' => $r->libelle ?? $r->name,
            ])->values()->all(),
            'doit_changer_mot_de_passe' => (bool) $u->doit_changer_mot_de_passe,
            'mfa_active' => $u->two_factor_confirmed_at !== null,
            'etag' => $u->updated_at?->toIso8601String(),
            'actions' => [
                'modifier' => $acteur?->can('update', $u) ?? false,
                'supprimer' => $acteur?->can('delete', $u) ?? false,
                'statut' => ($acteur?->can('update', $u) ?? false) && $acteur?->id !== $u->id,
                'reinitialiser' => $acteur?->can('update', $u) ?? false,
                'perimetre' => $acteur?->can('update', $u) ?? false,
            ],
        ];

        if (! $detail) {
            return $base;
        }

        return $base + [
            'telephone' => $u->telephone,
            'structure_id' => $u->structure_id,
            'fonction_id' => $u->fonction_id,
            'poste_id' => $u->poste_id,
            'fonction' => $u->fonction?->only(['id', 'libelle']),
            'poste' => $u->poste?->only(['id', 'libelle']),
            'profils' => $u->relationLoaded('profils')
                ? $u->profils->map(fn (Profil $p) => ['id' => $p->id, 'libelle' => $p->libelle])->values()->all()
                : [],
            'structures' => $u->relationLoaded('structures')
                ? $u->structures->map(fn (Structure $s) => ['id' => $s->id, 'code' => $s->code, 'libelle' => $s->libelle])->values()->all()
                : [],
            'scopes' => $u->relationLoaded('accessScopes')
                ? $u->accessScopes->map(fn ($s) => ['id' => $s->id, 'type' => $s->type, 'scope_id' => $s->scope_id])->values()->all()
                : [],
            'connexions' => $u->relationLoaded('loginLogs')
                ? $u->loginLogs->map(fn ($l) => [
                    'succes' => (bool) $l->succes,
                    'motif' => $l->motif,
                    'ip' => $l->ip,
                    'created_at' => $l->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'derniere_connexion_at' => $u->derniere_connexion_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function optionsFormulaire(): array
    {
        return [
            'statuts' => self::STATUTS,
            'types_perimetre' => [
                'global' => 'Global',
                'departement' => 'Département',
                'direction' => 'Direction',
                'service' => 'Service',
                'programme' => 'Programme',
                'exercice' => 'Exercice',
                'lecture_seule' => 'Lecture seule',
                'propres_dossiers' => 'Propres dossiers',
                'a_valider' => 'À valider',
            ],
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'fonctions' => Fonction::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
            'postes' => Poste::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'libelle', 'protege']),
            'profils' => Profil::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
        ];
    }

    private function href(string $name): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, [], false);
    }
}
