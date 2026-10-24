<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StructureRequest;
use App\Models\Structure;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\Api\IdempotenceActionService;
use App\Support\ReferentielAdministratifAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Référentiel organisationnel — session SPA. GET /structures (PAT) inchangé. */
class StructureApiController extends Controller
{
    /** @var array<string, string> */
    public const TYPES = [
        'presidence' => 'Présidence',
        'vice_presidence' => 'Vice-Présidence',
        'secretariat_general' => 'Secrétariat Général',
        'commissariat' => 'Commissariat',
        'departement' => 'Département',
        'direction' => 'Direction',
        'service' => 'Service',
        'cellule' => 'Cellule',
    ];

    public function __construct(
        private readonly AccessScopeService $scopes,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Structure::class);

        $query = Structure::query()
            ->with('parent:id,code,libelle,type')
            ->whereNull('deleted_at')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s
                ->where('libelle', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%')))
            ->orderBy('niveau')
            ->orderBy('ordre');

        $this->scopes->filtrerReferentielStructures($query, $request->user());

        $liste = $query->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));
        $user = $request->user();

        return response()->json([
            'types' => self::TYPES,
            'actions' => [
                'creer' => $user ? ReferentielAdministratifAuthorization::peut($user, 'creer') : false,
                'exporter' => $user ? ReferentielAdministratifAuthorization::peut($user, 'exporter') : false,
                'importer' => $user ? ReferentielAdministratifAuthorization::peut($user, 'importer') : false,
            ],
            'exports' => $this->liensExport($user),
            'href_organigramme' => $this->href('organigramme'),
            'data' => collect($liste->items())->map(fn (Structure $s) => $this->serialiser($s, $user, false))->values(),
            'meta' => [
                'total' => $liste->total(),
                'page' => $liste->currentPage(),
                'per_page' => $liste->perPage(),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Structure::class);
        $exclure = $request->integer('exclure') ?: null;

        return response()->json($this->optionsFormulaire($request, $exclure));
    }

    public function show(Request $request, Structure $structure): JsonResponse
    {
        $this->authorize('view', $structure);
        $this->assertPerimetre($request, $structure);
        $structure->load('parent:id,code,libelle,type', 'enfants:id,parent_id,code,libelle,type,actif', 'responsable:id,name,email');

        return response()->json([
            'structure' => $this->serialiser($structure, $request->user(), true),
        ]);
    }

    public function store(StructureRequest $request): JsonResponse
    {
        $this->authorize('create', Structure::class);
        $data = $request->validated();
        $data['niveau'] = $this->calculerNiveau($data['parent_id'] ?? null);

        $resultat = $this->idempotence->executer(
            $request,
            'structure.creer.'.$data['code'],
            function () use ($request, $data) {
                $structure = Structure::create($data);

                return [
                    'status' => 201,
                    'body' => [
                        'message' => 'Structure créée.',
                        'structure' => $this->serialiser($structure->fresh('parent', 'responsable'), $request->user(), true),
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(StructureRequest $request, Structure $structure): JsonResponse
    {
        $this->authorize('update', $structure);
        $this->assertPerimetre($request, $structure);
        $this->assertVersion($request, $structure);
        $data = $request->validated();
        $data['niveau'] = $this->calculerNiveau($data['parent_id'] ?? null);
        $structure->update($data);

        return response()->json([
            'message' => 'Structure mise à jour.',
            'structure' => $this->serialiser($structure->fresh(['parent', 'enfants', 'responsable']), $request->user(), true),
        ]);
    }

    public function destroy(Request $request, Structure $structure): JsonResponse
    {
        $this->authorize('delete', $structure);
        $this->assertPerimetre($request, $structure);
        $this->assertVersion($request, $structure);
        if ($structure->enfants()->exists()) {
            return response()->json([
                'message' => 'Impossible : cette structure a des structures rattachées.',
                'code' => 'transition_invalide',
            ], 422);
        }
        $structure->delete();

        return response()->json(['message' => 'Structure désactivée (suppression logique).']);
    }

    /** @return array<string, mixed> */
    private function serialiser(Structure $s, mixed $user, bool $detail): array
    {
        $base = [
            'id' => $s->id,
            'code' => $s->code,
            'libelle' => $s->libelle,
            'sigle' => $s->sigle,
            'type' => $s->type,
            'type_libelle' => self::TYPES[$s->type] ?? $s->type,
            'parent_id' => $s->parent_id,
            'parent' => $s->parent ? [
                'id' => $s->parent->id,
                'code' => $s->parent->code,
                'libelle' => $s->parent->libelle,
                'type' => $s->parent->type,
            ] : null,
            'niveau' => $s->niveau,
            'ordre' => $s->ordre,
            'actif' => (bool) $s->actif,
            'etag' => $s->updated_at?->toIso8601String(),
            'actions' => [
                'modifier' => $user?->can('update', $s) ?? false,
                'supprimer' => $user?->can('delete', $s) ?? false,
            ],
        ];

        if (! $detail) {
            return $base;
        }

        return $base + [
            'description' => $s->description,
            'responsable_user_id' => $s->responsable_user_id,
            'responsable' => $s->responsable ? $s->responsable->only(['id', 'name', 'email']) : null,
            'enfants' => $s->relationLoaded('enfants')
                ? $s->enfants->map(fn (Structure $e) => [
                    'id' => $e->id,
                    'code' => $e->code,
                    'libelle' => $e->libelle,
                    'type' => $e->type,
                    'actif' => (bool) $e->actif,
                ])->values()->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function optionsFormulaire(Request $request, ?int $exclure): array
    {
        $parents = Structure::query()
            ->whereNull('deleted_at')
            ->when($exclure, fn ($q) => $q->whereKeyNot($exclure))
            ->orderBy('libelle');
        $this->scopes->filtrerReferentielStructures($parents, $request->user());

        return [
            'types' => self::TYPES,
            'parents' => $parents->get(['id', 'code', 'libelle', 'type'])->map(fn (Structure $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'libelle' => $p->libelle,
                'type' => $p->type,
            ])->values(),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return list<array{format: string, href: string, libelle: string}> */
    private function liensExport(mixed $user): array
    {
        if (! $user || ! ReferentielAdministratifAuthorization::peut($user, 'exporter')) {
            return [];
        }

        return [
            ['format' => 'xlsx', 'libelle' => 'Annuaire Excel', 'href' => $this->href('structure.exporter.annuaire.excel')],
            ['format' => 'pdf', 'libelle' => 'Annuaire PDF', 'href' => $this->href('structure.exporter.annuaire.pdf')],
        ];
    }

    private function href(string $name): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, [], false);
    }

    private function calculerNiveau(?int $parentId): int
    {
        return $parentId ? ((int) Structure::find($parentId)?->niveau + 1) : 0;
    }

    private function assertPerimetre(Request $request, Structure $structure): void
    {
        abort_unless($this->scopes->dansPerimetre($request->user(), $structure->id), 403);
    }

    private function assertVersion(Request $request, Structure $structure): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }
        if ($attendu !== $structure->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'La structure a été modifiée. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }
}
