<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImmobilisationRequest;
use App\Http\Resources\ImmobilisationResource;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Immobilisation;
use App\Models\Structure;
use App\Services\Api\IdempotenceActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImmobilisationApiController extends Controller
{
    public function __construct(private readonly IdempotenceActionService $idempotence) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('immobilisation.voir'), 403);

        $items = Immobilisation::with('structure')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('categorie'), fn ($q) => $q->where('categorie', $request->categorie))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return ImmobilisationResource::collection($items)->additional([
            'categories' => Immobilisation::CATEGORIES,
            'statuts' => Immobilisation::STATUTS,
            'peut_creer' => (bool) $request->user()?->can('immobilisation.creer'),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('immobilisation.creer') || $request->user()?->can('immobilisation.modifier'), 403);

        return response()->json([
            'categories' => Immobilisation::CATEGORIES,
            'statuts' => Immobilisation::STATUTS,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'sigle', 'libelle']),
            'engagements' => Engagement::latest('id')->limit(100)->get(['id', 'numero', 'objet']),
        ]);
    }

    public function show(Request $request, Immobilisation $immobilisation): ImmobilisationResource
    {
        abort_unless($request->user()?->can('immobilisation.voir'), 403);
        $immobilisation->load(['structure', 'engagement', 'exercice']);

        return new ImmobilisationResource($immobilisation);
    }

    public function store(ImmobilisationRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('immobilisation.creer'), 403);
        $resultat = $this->idempotence->executer($request, 'immobilisation.store', function () use ($request) {
            $data = $request->validated();
            if (! filled($data['numero'] ?? null)) {
                unset($data['numero']);
            }
            $data['numero'] ??= $this->numero((int) $data['exercice_id']);
            $item = Immobilisation::create($data);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Immobilisation enregistrée.',
                    'code' => 'cree',
                    'immobilisation' => (new ImmobilisationResource($item->fresh()->load('structure')))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(ImmobilisationRequest $request, Immobilisation $immobilisation): JsonResponse
    {
        abort_unless($request->user()?->can('immobilisation.modifier'), 403);
        $immobilisation->update($request->validated());

        return response()->json([
            'message' => 'Immobilisation mise à jour.',
            'code' => 'mis_a_jour',
            'immobilisation' => (new ImmobilisationResource($immobilisation->fresh()->load('structure')))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Immobilisation $immobilisation): JsonResponse
    {
        abort_unless($request->user()?->can('immobilisation.supprimer'), 403);
        $immobilisation->delete();

        return response()->json(['message' => 'Immobilisation supprimée.', 'code' => 'supprime']);
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Immobilisation::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('IMM-%d-%03d', $annee, $seq);
    }
}
