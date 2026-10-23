<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Services\Workflow\WorkflowCorbeilleService;
use App\Services\WorkflowService;
use App\Support\WorkflowObjetUrls;
use App\Support\WorkflowRejetCategorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

/** Endpoints REST Workflow institutionnel (phase W-P3). */
class WorkflowApiController extends Controller
{
    public function __construct(
        private readonly WorkflowCorbeilleService $corbeille,
        private readonly WorkflowService $workflow,
    ) {}

    public function circuits(Request $request): JsonResource
    {
        $this->assertWorkflow($request);

        $circuits = Workflow::query()
            ->where('actif', true)
            ->withCount('instances')
            ->orderBy('code')
            ->get()
            ->map(fn (Workflow $w) => $this->formatCircuit($w));

        return JsonResource::collection($circuits);
    }

    public function circuit(Request $request, string $code): JsonResource
    {
        $this->assertWorkflow($request);

        $workflow = Workflow::with(['etapes', 'conditions', 'transitions'])
            ->where('code', $code)
            ->firstOrFail();

        return new JsonResource([
            ...$this->formatCircuit($workflow),
            'etapes' => $workflow->etapes->map(fn ($e) => [
                'ordre' => $e->ordre,
                'libelle' => $e->libelle,
                'role' => $e->role,
                'type_approbation' => $e->type_approbation ?? 'sequentiel',
                'groupe_parallele' => $e->groupe_parallele,
            ])->values(),
            'conditions' => $workflow->conditions->where('actif', true)->map(fn ($c) => [
                'code' => $c->code,
                'libelle' => $c->libelle,
                'expression' => $c->expression,
            ])->values(),
            'transitions' => $workflow->transitions->map(fn ($t) => [
                'source' => $t->etape_source_ordre,
                'cible' => $t->etape_cible_ordre,
                'type' => $t->type,
                'priorite' => $t->priorite,
                'condition_id' => $t->condition_id,
            ])->values(),
        ]);
    }

    public function instances(Request $request): JsonResource
    {
        $this->assertWorkflow($request);
        $user = $request->user();

        $taches = $this->corbeille->tachesPour($user)->map(fn (array $t) => [
            'id' => $t['instance']->id,
            'circuit' => $t['circuit'],
            'circuit_code' => $t['instance']->workflow?->code,
            'etape_ordre' => $t['etape']?->ordre,
            'etape_role' => $t['etape']?->role,
            'libelle' => $t['libelle'],
            'url' => $t['url'],
            'en_retard' => $t['en_retard'],
            'demarre_le' => $t['instance']->created_at?->toIso8601String(),
        ]);

        return JsonResource::collection($taches);
    }

    public function showInstance(Request $request, WorkflowInstance $instance): JsonResource
    {
        $this->assertWorkflow($request);
        abort_unless($this->workflow->peutAgir($instance, $request->user()) || $request->user()->can('workflow.audit'), 403);

        $instance->load(['workflow', 'etapes', 'objet', 'version']);

        return new JsonResource($this->formatInstance($instance, $request->user()));
    }

    public function decision(Request $request, WorkflowInstance $instance): JsonResponse
    {
        $this->assertWorkflow($request);
        abort_unless($request->user()->can('workflow.validate'), 403);

        $data = $request->validate([
            'decision' => ['required', 'in:valider,rejeter,renvoyer'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'categorie_rejet' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $this->workflow->traiterDecision(
                $instance,
                $request->user(),
                $data['decision'],
                $data['observation'] ?? null,
                $data['categorie_rejet'] ?? WorkflowRejetCategorie::TECHNIQUE,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Décision enregistrée.',
            'data' => $this->formatInstance($instance->fresh(['workflow', 'etapes', 'objet']), $request->user()),
        ]);
    }

    private function assertWorkflow(Request $request): void
    {
        abort_unless($request->user()?->can('workflow.view'), 403);
    }

    /** @return array<string, mixed> */
    private function formatCircuit(Workflow $workflow): array
    {
        return [
            'code' => $workflow->code,
            'libelle' => $workflow->libelle,
            'type_objet' => $workflow->type_objet,
            'version' => (int) ($workflow->version ?? 1),
            'published_at' => $workflow->published_at?->toIso8601String(),
            'instances_count' => $workflow->instances_count ?? $workflow->instances()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatInstance(WorkflowInstance $instance, $user): array
    {
        $etape = $instance->etapePourUtilisateur($user) ?? $instance->etapeCourante();

        return [
            'id' => $instance->id,
            'statut' => $instance->statut,
            'circuit' => $instance->workflow?->code,
            'version' => $instance->version?->numero ?? $instance->workflow?->version,
            'etape_courante' => $instance->etape_courante,
            'etape_agissable' => $etape ? [
                'ordre' => $etape->ordre,
                'role' => $etape->role,
                'statut' => $etape->statut,
                'echeance_le' => $etape->echeance_le?->toIso8601String(),
            ] : null,
            'objet' => [
                'type' => $instance->objet_type,
                'id' => $instance->objet_id,
                'libelle' => WorkflowObjetUrls::libelle($instance->objet),
                'url' => WorkflowObjetUrls::show($instance->objet),
            ],
            'etapes' => $instance->etapes->map(fn ($e) => [
                'ordre' => $e->ordre,
                'role' => $e->role,
                'statut' => $e->statut,
                'acteur_id' => $e->acteur_id,
                'decision_le' => $e->decision_le?->toIso8601String(),
            ])->values(),
        ];
    }
}
