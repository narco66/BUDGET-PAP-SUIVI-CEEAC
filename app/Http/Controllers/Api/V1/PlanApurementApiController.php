<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanApurementResource;
use App\Models\AppelFonds;
use App\Models\EcheanceApurement;
use App\Models\PlanApurement;
use App\Services\Api\IdempotenceActionService;
use App\Services\Recettes\PlanApurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PlanApurementApiController extends Controller
{
    public function __construct(
        private readonly PlanApurementService $service,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function store(Request $request, AppelFonds $appel): JsonResponse
    {
        $this->authorize('recouvrer', $appel);
        $data = $request->validate([
            'nombre_echeances' => ['required', 'integer', 'min:1', 'max:60'],
            'date_debut' => ['required', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        $resultat = $this->idempotence->executer($request, 'plan.creer.'.$appel->id, function () use ($request, $appel, $data) {
            $this->assertVersion($request, $appel->updated_at?->toIso8601String(), 'L’appel de fonds a été modifié. Rechargez avant d’agir.');
            try {
                $plan = $this->service->creer($appel->fresh(), $data, $request->user());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Plan d\'apurement établi.',
                    'code' => 'etabli',
                    'plan' => (new PlanApurementResource($this->recharger($plan)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function show(Request $request, PlanApurement $plan): PlanApurementResource
    {
        abort_unless($request->user()?->can('recette.voir'), 403);
        $plan->load(['appel.etatMembre', 'appel.sourceFinancement', 'echeances', 'etabliPar']);

        return new PlanApurementResource($plan);
    }

    public function reglerEcheance(Request $request, EcheanceApurement $echeance): JsonResponse
    {
        abort_unless($request->user()?->can('recette.recouvrement'), 403);
        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        $resultat = $this->idempotence->executer($request, 'echeance.regler.'.$echeance->id, function () use ($request, $echeance, $data) {
            $this->assertVersion($request, $echeance->updated_at?->toIso8601String(), 'L’échéance a été modifiée. Rechargez avant d’agir.');
            try {
                $this->service->reglerEcheance($echeance->fresh(), (float) $data['montant'], $data['observations'] ?? null);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            $plan = $this->recharger($echeance->fresh()->plan);

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Règlement de l\'échéance enregistré.',
                    'code' => 'regle',
                    'plan' => (new PlanApurementResource($plan))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function annuler(Request $request, PlanApurement $plan): JsonResponse
    {
        abort_unless($request->user()?->can('recette.recouvrement'), 403);
        abort_if($plan->estVerrouille() || $plan->statut !== 'actif', 403);

        $resultat = $this->idempotence->executer($request, 'plan.annuler.'.$plan->id, function () use ($request, $plan) {
            $this->assertVersion($request, $plan->updated_at?->toIso8601String(), 'Le plan d’apurement a été modifié. Rechargez avant d’agir.');
            try {
                $this->service->annuler($plan->fresh());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Plan d\'apurement annulé.',
                    'code' => 'annule',
                    'plan' => (new PlanApurementResource($this->recharger($plan)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    private function recharger(PlanApurement $plan): PlanApurement
    {
        return $plan->fresh()->load(['appel.etatMembre', 'appel.sourceFinancement', 'echeances', 'etabliPar']);
    }

    private function assertVersion(Request $request, ?string $actuel, string $message): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        if ($attendu !== $actuel) {
            abort(response()->json([
                'message' => $message,
                'code' => 'conflict',
            ], 409));
        }
    }
}
