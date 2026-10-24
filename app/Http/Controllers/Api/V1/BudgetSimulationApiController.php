<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetSimulation;
use App\Models\Structure;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\BudgetConsolidationService;
use App\Services\Budget\BudgetSimulationService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Scénarios what-if et comparaison (B3) — session SPA. */
class BudgetSimulationApiController extends Controller
{
    public function __construct(
        private readonly BudgetSimulationService $simulation,
        private readonly BudgetConsolidationService $consolidation,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function show(Request $request, Budget $budget): JsonResponse
    {
        abort_unless($request->user()->can('budget.voir') || $request->user()->can('ligne_budgetaire.voir'), 403);

        $user = $request->user();
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('comparer', []))));
        $comparaison = null;
        if (count($ids) >= 2) {
            try {
                $comparaison = $this->serialiserComparaison($this->simulation->comparer($budget, $ids));
            } catch (RuntimeException) {
                $comparaison = null;
            }
        }

        return response()->json([
            'budget' => (new BudgetResource($budget->load('exercice', 'structure', 'responsable', 'workflowActif')))->resolve($request),
            'simulations' => $this->simulation->simulations($budget)->map(fn (BudgetSimulation $s) => $this->serialiser($s))->values(),
            'consolidations' => $this->consolidation->historique($budget)->take(20)->map(fn ($c) => [
                'id' => $c->id,
                'version' => (int) $c->version,
                'lignes_count' => (int) $c->lignes_count,
                'montant_total' => Montant::api($c->montant_total),
                'statut' => $c->statut,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values(),
            'comparaison' => $comparaison,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'sigle', 'libelle']),
            'types' => BudgetSimulation::TYPES,
            'actions' => [
                'simuler' => $user?->can('simuler', $budget) ?? false,
            ],
        ]);
    }

    public function store(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('simuler', $budget);
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:200'],
            'hypothese' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:global_pct,structure_pct,enveloppe_plafond,ligne_ajustements'],
            'taux' => ['nullable', 'numeric'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'plafond' => ['nullable', 'numeric', 'min:0'],
        ]);

        $parametres = match ($data['type']) {
            'global_pct' => ['taux' => (float) ($data['taux'] ?? 0)],
            'structure_pct' => [
                'structure_id' => (int) ($data['structure_id'] ?? 0),
                'taux' => (float) ($data['taux'] ?? 0),
            ],
            'enveloppe_plafond' => ['plafond' => (float) ($data['plafond'] ?? 0)],
            default => [],
        };

        $resultat = $this->idempotence->executer(
            $request,
            'budget.sim.creer.'.$budget->id.'.'.md5($data['libelle'].$data['type'].json_encode($parametres)),
            function () use ($request, $budget, $data, $parametres) {
                $this->assertVersion($request, $budget);
                try {
                    $sim = $this->simulation->creer($budget, [
                        'libelle' => $data['libelle'],
                        'hypothese' => $data['hypothese'] ?? null,
                        'type' => $data['type'],
                        'parametres' => $parametres,
                    ], (int) $request->user()->id);
                    $sim = $this->simulation->calculer($sim, (int) $request->user()->id);
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 201,
                    'body' => [
                        'message' => 'Scénario créé et calculé.',
                        'code' => 'calcule',
                        'simulation' => $this->serialiser($sim->load('createur')),
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function calculer(Request $request, Budget $budget, BudgetSimulation $simulation): JsonResponse
    {
        $this->authorize('simuler', $budget);
        abort_unless((int) $simulation->budget_id === (int) $budget->id, 404);

        $resultat = $this->idempotence->executer($request, 'budget.sim.calculer.'.$simulation->id, function () use ($request, $budget, $simulation) {
            $this->assertVersion($request, $budget);
            try {
                $sim = $this->simulation->calculer($simulation, (int) $request->user()->id);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Scénario recalculé.',
                    'code' => 'calcule',
                    'simulation' => $this->serialiser($sim->load('createur')),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function retenir(Request $request, Budget $budget, BudgetSimulation $simulation): JsonResponse
    {
        $this->authorize('simuler', $budget);
        abort_unless((int) $simulation->budget_id === (int) $budget->id, 404);

        $resultat = $this->idempotence->executer($request, 'budget.sim.retenir.'.$simulation->id, function () use ($request, $budget, $simulation) {
            $this->assertVersion($request, $budget);
            try {
                $sim = $this->simulation->retenir($simulation, (int) $request->user()->id);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Scénario retenu comme référence.',
                    'code' => 'retenu',
                    'simulation' => $this->serialiser($sim->load('createur')),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    /** @return array<string, mixed> */
    private function serialiser(BudgetSimulation $s): array
    {
        return [
            'id' => $s->id,
            'code' => $s->code,
            'libelle' => $s->libelle,
            'hypothese' => $s->hypothese,
            'type' => $s->type,
            'statut' => $s->statut,
            'parametres' => $s->parametres,
            'montant_base' => Montant::api($s->montant_base),
            'montant_simule' => Montant::api($s->montant_simule),
            'ecart_absolu' => Montant::api($s->ecart_absolu),
            'ecart_pct' => Montant::api($s->ecart_pct),
            'calcule_le' => $s->calcule_le?->toIso8601String(),
            'createur' => $s->createur?->only(['id', 'name']),
        ];
    }

    /** @param  array<string, mixed>  $comparaison */
    private function serialiserComparaison(array $comparaison): array
    {
        $comparaison['montant_reference'] = Montant::api($comparaison['montant_reference'] ?? 0);
        $comparaison['ecart_max'] = Montant::api($comparaison['ecart_max'] ?? 0);
        $comparaison['ecart_min'] = Montant::api($comparaison['ecart_min'] ?? 0);
        $comparaison['scenarios'] = array_map(function (array $s) {
            $s = Montant::champs($s, ['montant_base', 'montant_simule', 'ecart_absolu', 'ecart_pct']);
            $s['par_structure'] = array_map(
                fn (array $row) => Montant::champs($row, ['montant_base', 'montant_simule', 'ecart']),
                $s['par_structure'] ?? []
            );

            return $s;
        }, $comparaison['scenarios'] ?? []);

        return $comparaison;
    }

    private function assertVersion(Request $request, Budget $budget): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        if ($attendu !== $budget->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'Le budget a été modifié. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }
}
