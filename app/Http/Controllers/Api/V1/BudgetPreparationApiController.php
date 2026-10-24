<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetPreparation;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\BudgetArbitrageService;
use App\Services\Budget\BudgetConsolidationService;
use App\Services\Budget\BudgetPreparationService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Préparation, validation hiérarchique, arbitrage et consolidation (B2) — session SPA. */
class BudgetPreparationApiController extends Controller
{
    public function __construct(
        private readonly BudgetPreparationService $preparation,
        private readonly BudgetArbitrageService $arbitrage,
        private readonly BudgetConsolidationService $consolidation,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function show(Request $request, Budget $budget): JsonResponse
    {
        abort_unless($request->user()->can('budget.voir') || $request->user()->can('ligne_budgetaire.voir'), 403);

        $structureId = $request->integer('structure') ?: null;
        $preps = $this->preparation->preparations($budget, $structureId);
        $perPage = min(100, max(1, (int) $request->integer('per_page', 50)));
        $page = max(1, (int) $request->integer('page', 1));
        $slice = $preps->forPage($page, $perPage)->values();
        $user = $request->user();

        return response()->json([
            'budget' => (new BudgetResource($budget->load('exercice', 'structure', 'responsable', 'workflowActif')))->resolve($request),
            'synthese' => Montant::champs($this->preparation->synthese($budget), [
                'montant_demande', 'montant_propose', 'montant_arbitre',
            ]),
            'par_structure' => $this->consolidation->parStructure($budget)->map(fn ($row) => [
                'structure_id' => $row->structure_id,
                'libelle' => $row->structure?->libelle,
                'sigle' => $row->structure?->sigle,
                'lignes' => (int) $row->lignes,
                'demande' => Montant::api($row->demande),
                'alloue' => Montant::api($row->alloue),
            ])->values(),
            'preparations' => $slice->map(fn (BudgetPreparation $p) => $this->serialiser($p))->all(),
            'meta' => [
                'total' => $preps->count(),
                'page' => $page,
                'per_page' => $perPage,
            ],
            'historique' => $this->arbitrage->historique($budget)->take(20)->map(fn ($a) => [
                'id' => $a->id,
                'reference' => $a->preparation?->reference,
                'montant_avant' => Montant::api($a->montant_avant),
                'montant_apres' => Montant::api($a->montant_apres),
                'motif' => $a->motif,
                'auteur' => $a->auteur?->only(['id', 'name']),
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values(),
            'actions' => [
                'preparer' => $user?->can('preparer', $budget) ?? false,
                'soumettre' => $user?->can('preparer', $budget) ?? false,
                'valider_direction' => $user?->can('validerPreparation', [$budget, 'direction']) ?? false,
                'valider_departement' => $user?->can('validerPreparation', [$budget, 'departement']) ?? false,
                'valider_dppb' => $user?->can('validerPreparation', [$budget, 'dppb']) ?? false,
                'arbitrer' => $user?->can('arbitrer', $budget) ?? false,
                'consolider' => $user?->can('consolider', $budget) ?? false,
                'simuler' => $user?->can('simuler', $budget) ?? false,
            ],
            'liens' => [
                'simulations' => $this->href('budget.enveloppe.simulations', $budget),
                'revisions' => $this->href('budget.enveloppe.revisions', $budget),
                'rapport' => $this->href('budget.enveloppe.rapport', $budget),
            ],
        ]);
    }

    public function initialiser(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('preparer', $budget);

        $resultat = $this->idempotence->executer($request, 'budget.prep.init.'.$budget->id, function () use ($request, $budget) {
            $this->assertVersion($request, $budget);
            try {
                $count = $this->preparation->initialiser($budget);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => "Préparations initialisées ({$count} nouvelle(s) ligne(s)).",
                    'code' => 'initialise',
                    'creees' => $count,
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(Request $request, Budget $budget, BudgetPreparation $preparation): JsonResponse
    {
        $this->authorize('preparer', $budget);
        abort_unless((int) $preparation->budget_id === (int) $budget->id, 404);

        $data = $request->validate([
            'montant_demande' => ['nullable', 'numeric', 'min:0'],
            'montant_propose' => ['nullable', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $prep = $this->preparation->mettreAJour($preparation, $data, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'Proposition mise à jour.',
            'code' => 'modifie',
            'preparation' => $this->serialiser($prep->load(['structure', 'ligneBudgetaire'])),
        ]);
    }

    public function soumettre(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('preparer', $budget);
        $data = $request->validate([
            'structure_id' => ['required', 'exists:structures,id'],
        ]);

        $resultat = $this->idempotence->executer(
            $request,
            'budget.prep.soumettre.'.$budget->id.'.'.$data['structure_id'],
            function () use ($request, $budget, $data) {
                $this->assertVersion($request, $budget);
                try {
                    $count = $this->preparation->soumettreStructure($budget, (int) $data['structure_id'], (int) $request->user()->id);
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 200,
                    'body' => [
                        'message' => "{$count} proposition(s) soumise(s) par le service.",
                        'code' => 'soumis',
                        'soumises' => $count,
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function valider(Request $request, Budget $budget): JsonResponse
    {
        $data = $request->validate([
            'niveau' => ['required', 'in:direction,departement,dppb'],
            'structure_id' => ['nullable', 'exists:structures,id'],
        ]);
        $this->authorize('validerPreparation', [$budget, $data['niveau']]);

        $resultat = $this->idempotence->executer(
            $request,
            'budget.prep.valider.'.$budget->id.'.'.$data['niveau'].'.'.($data['structure_id'] ?? 'all'),
            function () use ($request, $budget, $data) {
                $this->assertVersion($request, $budget);
                try {
                    $count = $this->preparation->validerNiveau(
                        $budget,
                        $data['niveau'],
                        (int) $request->user()->id,
                        isset($data['structure_id']) ? (int) $data['structure_id'] : null,
                    );
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 200,
                    'body' => [
                        'message' => "{$count} proposition(s) validée(s) ({$data['niveau']}).",
                        'code' => 'valide',
                        'validees' => $count,
                        'niveau' => $data['niveau'],
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function arbitrer(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('arbitrer', $budget);
        $data = $request->validate([
            'preparation_id' => ['required', 'exists:budget_preparations,id'],
            'montant_apres' => ['required', 'numeric', 'min:0'],
            'motif' => ['required', 'string', 'max:1000'],
        ]);

        $prep = BudgetPreparation::where('budget_id', $budget->id)->findOrFail($data['preparation_id']);

        $resultat = $this->idempotence->executer(
            $request,
            'budget.prep.arbitrer.'.$budget->id.'.'.$prep->id,
            function () use ($request, $budget, $prep, $data) {
                $this->assertVersion($request, $budget);
                try {
                    $this->arbitrage->arbitrer($prep, (float) $data['montant_apres'], $data['motif'], (int) $request->user()->id);
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Arbitrage enregistré.',
                        'code' => 'arbitre',
                        'preparation' => $this->serialiser($prep->fresh(['structure', 'ligneBudgetaire'])),
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function consolider(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('consolider', $budget);

        $resultat = $this->idempotence->executer($request, 'budget.prep.consolider.'.$budget->id, function () use ($request, $budget) {
            $this->assertVersion($request, $budget);
            try {
                $stats = $this->consolidation->consolider($budget, (int) $request->user()->id);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => "Consolidation : {$stats['lignes']} ligne(s).",
                    'code' => 'consolide',
                    'lignes' => $stats['lignes'],
                    'montant_total' => Montant::api($stats['montant_total'] ?? 0),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    /** @return array<string, mixed> */
    private function serialiser(BudgetPreparation $p): array
    {
        return [
            'id' => $p->id,
            'reference' => $p->reference,
            'statut' => $p->statut,
            'montant_demande' => Montant::api($p->montant_demande),
            'montant_propose' => Montant::api($p->montant_propose),
            'montant_arbitre' => $p->montant_arbitre !== null ? Montant::api($p->montant_arbitre) : null,
            'observations' => $p->observations,
            'modifiable' => $p->estModifiable(),
            'structure' => $p->structure?->only(['id', 'code', 'libelle', 'sigle']),
            'ligne_budgetaire_id' => $p->ligne_budgetaire_id,
        ];
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

    private function href(string $name, mixed $params = []): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, $params, false);
    }
}
