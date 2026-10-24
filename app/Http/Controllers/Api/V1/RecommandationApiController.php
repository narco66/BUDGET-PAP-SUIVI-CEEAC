<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecommandationResource;
use App\Models\Exercice;
use App\Models\Recommandation;
use App\Models\Structure;
use App\Models\User;
use App\Services\Api\IdempotenceActionService;
use App\Services\Notifications\SuiviEvalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class RecommandationApiController extends Controller
{
    public function __construct(private readonly IdempotenceActionService $idempotence) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('recommandation.voir'), 403);

        $items = Recommandation::with(['structure', 'responsable'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return RecommandationResource::collection($items)->additional([
            'sources' => Recommandation::SOURCES,
            'statuts' => Recommandation::STATUTS,
            'peut_creer' => (bool) $request->user()?->can('recommandation.creer'),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('recommandation.creer') || $request->user()?->can('recommandation.modifier'), 403);

        return response()->json([
            'sources' => Recommandation::SOURCES,
            'categories' => Recommandation::CATEGORIES,
            'priorites' => Recommandation::PRIORITES,
            'statuts' => Recommandation::STATUTS,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'sigle', 'libelle']),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Recommandation $recommandation): RecommandationResource
    {
        abort_unless($request->user()?->can('recommandation.voir'), 403);
        $recommandation->load(['structure', 'responsable', 'exercice']);

        return new RecommandationResource($recommandation);
    }

    public function store(Request $request, SuiviEvalNotificationService $notifications): JsonResponse
    {
        abort_unless($request->user()?->can('recommandation.creer'), 403);
        $resultat = $this->idempotence->executer($request, 'recommandation.store', function () use ($request, $notifications) {
            $data = $this->valider($request);
            if (! filled($data['numero'] ?? null)) {
                unset($data['numero']);
            }
            $data['numero'] ??= $this->numero((int) $data['exercice_id']);
            $recommandation = Recommandation::create($data);
            $notifications->notifierRecommandationCreee($recommandation);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Recommandation enregistrée.',
                    'code' => 'cree',
                    'recommandation' => (new RecommandationResource($recommandation->fresh()->load(['structure', 'responsable'])))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(Request $request, Recommandation $recommandation): JsonResponse
    {
        abort_unless($request->user()?->can('recommandation.modifier'), 403);
        $recommandation->update($this->valider($request, $recommandation));

        return response()->json([
            'message' => 'Recommandation mise à jour.',
            'code' => 'mis_a_jour',
            'recommandation' => (new RecommandationResource($recommandation->fresh()->load(['structure', 'responsable'])))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Recommandation $recommandation): JsonResponse
    {
        abort_unless($request->user()?->can('recommandation.supprimer'), 403);
        $recommandation->delete();

        return response()->json(['message' => 'Recommandation supprimée.', 'code' => 'supprime']);
    }

    private function valider(Request $request, ?Recommandation $rec = null): array
    {
        return $request->validate([
            'numero' => ['nullable', 'string', 'max:40', Rule::unique('recommandations', 'numero')->ignore($rec?->id)],
            'exercice_id' => ['required', 'exists:exercices,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'responsable_user_id' => ['nullable', 'exists:users,id'],
            'source' => ['required', Rule::in(array_keys(Recommandation::SOURCES))],
            'categorie' => ['nullable', Rule::in(array_keys(Recommandation::CATEGORIES))],
            'evaluation_periodique_id' => ['nullable', 'exists:evaluations_periodiques,id'],
            'reference_reunion' => ['nullable', 'string', 'max:255'],
            'date_emission' => ['nullable', 'date'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priorite' => ['required', Rule::in(array_keys(Recommandation::PRIORITES))],
            'echeance' => ['nullable', 'date'],
            'statut' => ['required', Rule::in(array_keys(Recommandation::STATUTS))],
            'taux_execution' => ['required', 'numeric', 'min:0', 'max:100'],
            'observations' => ['nullable', 'string'],
        ]);
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Recommandation::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('REC-%d-%03d', $annee, $seq);
    }
}
