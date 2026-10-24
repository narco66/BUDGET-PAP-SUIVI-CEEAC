<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ged\ConnaissanceRequest;
use App\Models\Ged\Connaissance;
use App\Models\Ged\GedDocument;
use App\Services\Api\IdempotenceActionService;
use App\Services\Ged\ConnaissanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class ConnaissanceApiController extends Controller
{
    public function __construct(
        private readonly ConnaissanceService $service,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.voir'), 403);
        $user = $request->user();

        $items = Connaissance::with('auteur')
            ->visiblesPour($user)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->when($request->filled('statut') && $this->peut($request, 'ged.admin'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('titre', 'like', $term)->orWhere('resume', 'like', $term)->orWhere('contenu', 'like', $term);
                });
            })
            ->latest('publie_le')
            ->latest('id')
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 20))));

        $visibles = Connaissance::visiblesPour($user);

        return response()->json([
            'data' => $items->getCollection()->map(fn (Connaissance $c) => $this->resume($c, $request))->values()->all(),
            'meta' => [
                'total' => $items->total(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
            'synthese' => [
                'total' => (clone $visibles)->count(),
                'procedures' => (clone $visibles)->where('type', 'procedure')->where('statut', 'publie')->count(),
                'wiki' => (clone $visibles)->where('type', 'wiki')->where('statut', 'publie')->count(),
            ],
            'peut_creer' => $this->peut($request, 'ged.creer'),
            'types' => $this->mapOptions(Connaissance::TYPES),
            'modules' => $this->mapOptions(Connaissance::MODULES),
            'statuts' => $this->mapOptions(Connaissance::STATUTS),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.voir'), 403);

        return response()->json([
            'types' => $this->mapOptions(Connaissance::TYPES),
            'modules' => $this->mapOptions(Connaissance::MODULES),
            'statuts' => $this->mapOptions(Connaissance::STATUTS),
            'documents' => $this->peut($request, 'ged.creer') || $this->peut($request, 'ged.modifier')
                ? GedDocument::query()->latest('id')->limit(50)->get(['id', 'titre', 'reference', 'uuid'])
                : [],
        ]);
    }

    public function show(Request $request, Connaissance $connaissance): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.voir'), 403);
        abort_unless(
            $connaissance->statut === 'publie'
            || $connaissance->auteur_id === $request->user()?->id
            || $this->peut($request, 'ged.admin'),
            404
        );

        $connaissance->load(['auteur', 'publiePar', 'documentGed', 'versions.auteur']);

        return response()->json(['data' => $this->fiche($connaissance, $request)]);
    }

    public function store(ConnaissanceRequest $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.creer'), 403);

        $resultat = $this->idempotence->executer($request, 'ged.connaissance.creer', function () use ($request) {
            $item = $this->service->creer($request->donnees(), $request->user());

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Contenu enregistré en brouillon.',
                    'data' => $this->fiche($item->load(['auteur', 'publiePar', 'documentGed', 'versions.auteur']), $request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(ConnaissanceRequest $request, Connaissance $connaissance): JsonResponse
    {
        abort_unless($connaissance->estModifiablePar($request->user()), 403);
        $item = $this->service->mettreAJour($connaissance, $request->donnees(), $request->user());

        return response()->json([
            'message' => 'Contenu mis à jour (v'.$item->version.').',
            'data' => $this->fiche($item->load(['auteur', 'publiePar', 'documentGed', 'versions.auteur']), $request),
        ]);
    }

    public function destroy(Request $request, Connaissance $connaissance): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.admin'), 403);
        $connaissance->delete();

        return response()->json(['message' => 'Contenu supprimé.']);
    }

    public function publier(Request $request, Connaissance $connaissance): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.publier'), 403);
        abort_unless($connaissance->estModifiablePar($request->user()), 403);

        $resultat = $this->idempotence->executer($request, 'ged.connaissance.publier.'.$connaissance->id, function () use ($request, $connaissance) {
            $item = $this->service->publier($connaissance, $request->user());

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Contenu publié.',
                    'data' => $this->fiche($item->load(['auteur', 'publiePar', 'documentGed', 'versions.auteur']), $request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function archiver(Request $request, Connaissance $connaissance): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.publier') || $this->peut($request, 'ged.admin'), 403);
        $item = $this->service->archiver($connaissance);

        return response()->json([
            'message' => 'Contenu archivé.',
            'data' => $this->fiche($item->load(['auteur', 'publiePar', 'documentGed', 'versions.auteur']), $request),
        ]);
    }

    /** @return array<string, mixed> */
    private function resume(Connaissance $c, Request $request): array
    {
        return [
            'id' => $c->id,
            'reference' => $c->reference,
            'titre' => $c->titre,
            'type' => $c->type,
            'type_libelle' => Connaissance::TYPES[$c->type] ?? $c->type,
            'module' => $c->module,
            'module_libelle' => Connaissance::MODULES[$c->module] ?? $c->module,
            'statut' => $c->statut,
            'statut_libelle' => Connaissance::STATUTS[$c->statut] ?? $c->statut,
            'version' => $c->version,
            'resume' => $c->resume,
            'auteur' => $c->auteur?->name,
            'publie_le' => $c->publie_le?->toIso8601String(),
            'actions' => [
                'modifier' => $c->estModifiablePar($request->user()),
                'publier' => $this->peut($request, 'ged.publier') && $c->estModifiablePar($request->user()) && $c->statut !== 'publie',
                'archiver' => ($this->peut($request, 'ged.publier') || $this->peut($request, 'ged.admin')) && $c->statut !== 'archive',
                'supprimer' => $this->peut($request, 'ged.admin'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function fiche(Connaissance $c, Request $request): array
    {
        return array_merge($this->resume($c, $request), [
            'categorie' => $c->categorie,
            'contenu' => $c->contenu,
            'contenu_html' => Str::markdown((string) $c->contenu),
            'tags' => $c->tags ?? [],
            'ged_document_id' => $c->ged_document_id,
            'document_ged' => $c->documentGed ? [
                'id' => $c->documentGed->id,
                'uuid' => $c->documentGed->uuid,
                'titre' => $c->documentGed->titre,
                'reference' => $c->documentGed->reference,
            ] : null,
            'publie_par' => $c->publiePar?->name,
            'versions' => $c->versions->take(8)->map(fn ($v) => [
                'version' => $v->version,
                'auteur' => $v->auteur?->name,
                'created_at' => $v->created_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    /** @param  array<string, string>  $map */
    private function mapOptions(array $map): array
    {
        return collect($map)->map(fn ($libelle, $code) => [
            'code' => $code,
            'libelle' => $libelle,
        ])->values()->all();
    }

    private function peut(Request $request, string $ability): bool
    {
        try {
            return (bool) $request->user()?->can($ability);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
