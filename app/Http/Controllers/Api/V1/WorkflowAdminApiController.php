<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Services\Api\IdempotenceActionService;
use App\Services\Workflow\WorkflowCircuitAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowAdminApiController extends Controller
{
    public function __construct(
        private readonly WorkflowCircuitAdminService $circuits,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('workflow.admin'), 403);

        return response()->json([
            'data' => $this->circuits->lister()->map(fn (Workflow $w) => $this->circuits->serialiser($w))->values()->all(),
            ...$this->circuits->options($request->user()),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('workflow.admin') || $request->user()?->can('workflow.create'), 403);

        return response()->json($this->circuits->options($request->user()));
    }

    public function show(Request $request, Workflow $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('workflow.admin') || $request->user()?->can('workflow.update'), 403);

        $workflow->load('etapes')->loadCount('instances');

        return response()->json([
            'data' => $this->circuits->serialiser($workflow, true),
            ...$this->circuits->options($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('workflow.create'), 403);

        $resultat = $this->idempotence->executer($request, 'workflow.circuit.creer', function () use ($request) {
            $workflow = $this->circuits->creer($request);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Circuit créé.',
                    'code' => 'cree',
                    'data' => $this->circuits->serialiser($workflow, true),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(Request $request, Workflow $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('workflow.update'), 403);

        $resultat = $this->idempotence->executer($request, 'workflow.circuit.modifier.'.$workflow->id, function () use ($request, $workflow) {
            $modifie = $this->circuits->modifier($request, $workflow);

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Circuit enregistré.',
                    'code' => 'modifie',
                    'data' => $this->circuits->serialiser($modifie, true),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function toggle(Request $request, Workflow $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('workflow.update'), 403);

        $resultat = $this->idempotence->executer($request, 'workflow.circuit.toggle.'.$workflow->id, function () use ($workflow) {
            $modifie = $this->circuits->basculer($workflow);

            return [
                'status' => 200,
                'body' => [
                    'message' => $modifie->actif ? 'Circuit activé.' : 'Circuit désactivé.',
                    'code' => 'modifie',
                    'data' => $this->circuits->serialiser($modifie, true),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }
}
