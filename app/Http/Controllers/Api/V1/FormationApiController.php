<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormationRequest;
use App\Http\Resources\FormationResource;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Formation;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Services\Api\IdempotenceActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FormationApiController extends Controller
{
    public function __construct(private readonly IdempotenceActionService $idempotence) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('formation.voir'), 403);

        $items = Formation::with(['structure', 'sourceFinancement'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return FormationResource::collection($items)->additional([
            'types' => Formation::TYPES,
            'statuts' => Formation::STATUTS,
            'peut_creer' => (bool) $request->user()?->can('formation.creer'),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('formation.creer') || $request->user()?->can('formation.modifier'), 403);

        return response()->json([
            'types' => Formation::TYPES,
            'statuts' => Formation::STATUTS,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'sigle', 'libelle']),
            'sources' => SourceFinancement::orderBy('libelle')->get(['id', 'code', 'libelle']),
            'engagements' => Engagement::latest('id')->limit(100)->get(['id', 'numero', 'objet']),
        ]);
    }

    public function show(Request $request, Formation $formation): FormationResource
    {
        abort_unless($request->user()?->can('formation.voir'), 403);
        $formation->load(['structure', 'sourceFinancement', 'engagement']);

        return new FormationResource($formation);
    }

    public function store(FormationRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('formation.creer'), 403);
        $resultat = $this->idempotence->executer($request, 'formation.store', function () use ($request) {
            $data = $request->validated();
            if (! filled($data['numero'] ?? null)) {
                unset($data['numero']);
            }
            $data['numero'] ??= $this->numero((int) $data['exercice_id']);
            $formation = Formation::create($data);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Formation enregistrée.',
                    'code' => 'cree',
                    'formation' => (new FormationResource($formation->fresh()->load(['structure'])))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(FormationRequest $request, Formation $formation): JsonResponse
    {
        abort_unless($request->user()?->can('formation.modifier'), 403);
        $formation->update($request->validated());

        return response()->json([
            'message' => 'Formation mise à jour.',
            'code' => 'mis_a_jour',
            'formation' => (new FormationResource($formation->fresh()->load(['structure'])))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Formation $formation): JsonResponse
    {
        abort_unless($request->user()?->can('formation.supprimer'), 403);
        $formation->delete();

        return response()->json(['message' => 'Formation supprimée.', 'code' => 'supprime']);
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Formation::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('FOR-%d-%03d', $annee, $seq);
    }
}
