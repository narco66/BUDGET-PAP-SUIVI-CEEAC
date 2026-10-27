<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EngagementRequest;
use App\Http\Requests\WorkflowDecisionSansRenvoiRequest;
use App\Http\Resources\EngagementResource;
use App\Models\Engagement;
use App\Services\Api\IdempotenceActionService;
use App\Services\DocumentPdf;
use App\Services\EngagementService;
use App\Services\WebhookService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EngagementApiController extends Controller
{
    public function __construct(
        private readonly EngagementService $engagements,
        private readonly WorkflowService $workflow,
        private readonly WebhookService $webhooks,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Engagement::class);

        $query = Engagement::with('exercice', 'createur', 'imputations.ligneBudgetaire', 'workflowActif')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($terme) {
                    $inner->where('numero', 'like', $terme)->orWhere('objet', 'like', $terme);
                });
            })
            ->latest();
        app(\App\Services\AccessScopeService::class)->filtrerDossiersDepense($query, $request->user());

        return EngagementResource::collection(
            $query->paginate(min(100, max(1, (int) $request->integer('per_page', 20))))
        );
    }

    public function show(Request $request, Engagement $engagement): EngagementResource
    {
        $this->authorize('view', $engagement);
        $engagement->load([
            'exercice', 'createur', 'expressionBesoin',
            'imputations.ligneBudgetaire', 'liquidations', 'workflowActif.etapes.acteur',
        ]);

        return new EngagementResource($engagement);
    }

    public function store(EngagementRequest $request): JsonResponse
    {
        $this->authorize('create', Engagement::class);

        try {
            $engagement = $this->engagements->creer(
                $request->safe()->only(['exercice_id', 'expression_besoin_id', 'type', 'objet', 'beneficiaire', 'date_engagement']),
                $request->validated('imputations'),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'Engagement créé (brouillon).',
            'code' => 'cree',
            'engagement' => (new EngagementResource($this->recharger($engagement)))->resolve($request),
        ], 201);
    }

    public function soumettre(Request $request, Engagement $engagement): JsonResponse
    {
        abort_unless($request->user()->can('engagement.creer'), 403);

        $resultat = $this->idempotence->executer($request, 'engagement.soumettre.'.$engagement->id, function () use ($request, $engagement) {
            $this->assertVersion($request, $engagement);
            try {
                $this->engagements->soumettre($engagement, $request->user());
            } catch (RuntimeException $e) {
                $engagement->refresh();

                return ['status' => 422, 'body' => [
                    'message' => $e->getMessage(),
                    'code' => 'credits_ou_transition',
                    'diagnostic' => $engagement->diagnostic_credits,
                    'engagement' => (new EngagementResource($this->recharger($engagement)))->resolve($request),
                ]];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Engagement soumis au visa (crédits réservés).',
                    'code' => 'soumis',
                    'engagement' => (new EngagementResource($this->recharger($engagement)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function agir(WorkflowDecisionSansRenvoiRequest $request, Engagement $engagement): JsonResponse
    {
        $this->authorize('agir', $engagement);
        $data = $request->validated();
        $instance = $engagement->workflowActif;
        if (! $instance) {
            return response()->json(['message' => 'Aucun circuit actif.', 'code' => 'sans_circuit'], 422);
        }

        $resultat = $this->idempotence->executer($request, 'engagement.decision.'.$engagement->id, function () use ($request, $engagement, $instance, $data) {
            $this->assertVersion($request, $engagement);
            try {
                app(\App\Services\Workflow\DecisionMetierService::class)->decider($engagement, $request->user(), $data);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Décision enregistrée.',
                    'code' => 'decision',
                    'engagement' => (new EngagementResource($this->recharger($engagement)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function degager(Request $request, Engagement $engagement): JsonResponse
    {
        $this->authorize('annuler', $engagement);
        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $this->engagements->degager($engagement, (float) $data['montant'], $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'credits_ou_transition'], 422);
        }

        return response()->json([
            'message' => 'Reliquat non liquidé dégagé. Les montants déjà liquidés sont inchangés.',
            'code' => 'degage',
            'engagement' => (new EngagementResource($this->recharger($engagement)))->resolve($request),
        ]);
    }

    public function pdf(Engagement $engagement, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $engagement);

        return $pdf->engagement($engagement)->stream("engagement_{$engagement->numero}.pdf");
    }

    public function certificat(Engagement $engagement, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $engagement);

        return $pdf->certificatReservation($engagement)->stream("certificat_{$engagement->numero}.pdf");
    }

    private function recharger(Engagement $engagement): Engagement
    {
        return $engagement->fresh()->load('imputations.ligneBudgetaire', 'liquidations', 'workflowActif', 'expressionBesoin');
    }

    private function assertVersion(Request $request, Engagement $engagement): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        if ($attendu !== $engagement->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'L’engagement a été modifié. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }
}
