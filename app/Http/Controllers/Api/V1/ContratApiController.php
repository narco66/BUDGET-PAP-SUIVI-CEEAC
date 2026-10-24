<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContratRequest;
use App\Http\Resources\ContratResource;
use App\Models\Contrat;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Marche;
use App\Models\Structure;
use App\Services\Api\IdempotenceActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContratApiController extends Controller
{
    public function __construct(private readonly IdempotenceActionService $idempotence) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('contrat.voir'), 403);

        $items = Contrat::with(['marche', 'engagement', 'structure'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($terme) {
                    $inner->where('numero', 'like', $terme)->orWhere('objet', 'like', $terme)->orWhere('titulaire', 'like', $terme);
                });
            })
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return ContratResource::collection($items)->additional([
            'statuts' => Contrat::STATUTS,
            'peut_creer' => (bool) $request->user()?->can('contrat.creer'),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('contrat.creer') || $request->user()?->can('contrat.modifier'), 403);

        return response()->json([
            'types' => Contrat::TYPES,
            'statuts' => Contrat::STATUTS,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'sigle', 'libelle']),
            'marches' => Marche::query()->latest('id')->limit(100)->get(['id', 'numero', 'objet']),
            'engagements' => Engagement::query()->latest('id')->limit(100)->get(['id', 'numero', 'objet']),
        ]);
    }

    public function show(Request $request, Contrat $contrat): ContratResource
    {
        abort_unless($request->user()?->can('contrat.voir'), 403);
        $contrat->load(['marche', 'engagement.expressionBesoin', 'structure', 'exercice']);

        return new ContratResource($contrat);
    }

    public function store(ContratRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('contrat.creer'), 403);
        $resultat = $this->idempotence->executer($request, 'contrat.store', function () use ($request) {
            $data = $request->validated();
            if (! filled($data['numero'] ?? null)) {
                unset($data['numero']);
            }
            $data['numero'] ??= $this->numero((int) $data['exercice_id']);
            $contrat = Contrat::create($data);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Contrat enregistré.',
                    'code' => 'cree',
                    'contrat' => (new ContratResource($contrat->fresh()->load(['marche', 'structure'])))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(ContratRequest $request, Contrat $contrat): JsonResponse
    {
        abort_unless($request->user()?->can('contrat.modifier'), 403);
        $contrat->update($request->validated());

        return response()->json([
            'message' => 'Contrat mis à jour.',
            'code' => 'mis_a_jour',
            'contrat' => (new ContratResource($contrat->fresh()->load(['marche', 'structure'])))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Contrat $contrat): JsonResponse
    {
        abort_unless($request->user()?->can('contrat.supprimer'), 403);
        $contrat->delete();

        return response()->json(['message' => 'Contrat supprimé.', 'code' => 'supprime']);
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Contrat::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('CTR-%d-%03d', $annee, $seq);
    }
}
