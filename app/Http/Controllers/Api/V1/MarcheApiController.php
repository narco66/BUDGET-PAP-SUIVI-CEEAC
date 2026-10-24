<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarcheRequest;
use App\Http\Resources\MarcheResource;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Marche;
use App\Models\Structure;
use App\Services\Api\IdempotenceActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarcheApiController extends Controller
{
    public function __construct(private readonly IdempotenceActionService $idempotence) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('marche.voir'), 403);

        $items = Marche::with(['engagement', 'expressionBesoin', 'structure', 'contrat'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($terme) {
                    $inner->where('numero', 'like', $terme)->orWhere('objet', 'like', $terme);
                });
            })
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return MarcheResource::collection($items)->additional([
            'statuts' => Marche::STATUTS,
            'peut_creer' => (bool) $request->user()?->can('marche.creer'),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('marche.creer') || $request->user()?->can('marche.modifier'), 403);

        return response()->json([
            'types' => Marche::TYPES,
            'modes' => Marche::MODES,
            'statuts' => Marche::STATUTS,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'sigle', 'libelle']),
            'engagements' => Engagement::query()->where('type', 'marche')->latest('id')->limit(100)->get(['id', 'numero', 'objet']),
            'besoins' => ExpressionBesoin::query()->latest('id')->limit(100)->get(['id', 'numero', 'objet']),
        ]);
    }

    public function show(Request $request, Marche $marche): MarcheResource
    {
        abort_unless($request->user()?->can('marche.voir'), 403);
        $marche->load(['exercice', 'engagement.expressionBesoin', 'expressionBesoin', 'structure', 'contrat']);

        return new MarcheResource($marche);
    }

    public function store(MarcheRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('marche.creer'), 403);
        $resultat = $this->idempotence->executer($request, 'marche.store', function () use ($request) {
            $data = $request->validated();
            if (! filled($data['numero'] ?? null)) {
                unset($data['numero']);
            }
            $data['numero'] ??= $this->numero((int) $data['exercice_id']);
            $marche = Marche::create($data);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Marché enregistré.',
                    'code' => 'cree',
                    'marche' => (new MarcheResource($marche->fresh()->load(['structure', 'contrat'])))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(MarcheRequest $request, Marche $marche): JsonResponse
    {
        abort_unless($request->user()?->can('marche.modifier'), 403);
        $marche->update($request->validated());

        return response()->json([
            'message' => 'Marché mis à jour.',
            'code' => 'mis_a_jour',
            'marche' => (new MarcheResource($marche->fresh()->load(['structure', 'contrat'])))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Marche $marche): JsonResponse
    {
        abort_unless($request->user()?->can('marche.supprimer'), 403);
        $marche->delete();

        return response()->json(['message' => 'Marché supprimé.', 'code' => 'supprime']);
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Marche::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('MAR-%d-%03d', $annee, $seq);
    }
}
