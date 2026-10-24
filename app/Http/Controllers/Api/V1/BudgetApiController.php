<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\BudgetEnveloppeRequest;
use App\Http\Requests\WorkflowDecisionRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\PrevisionRecette;
use App\Models\Structure;
use App\Models\User;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\BudgetAuditService;
use App\Services\Budget\BudgetEnveloppeService;
use App\Services\Budget\BudgetNotificationService;
use App\Services\Budget\BudgetReferentielService;
use App\Services\Budget\SuiviFinancierService;
use App\Services\TableauBordService;
use App\Services\WorkflowService;
use App\Support\Montant;
use App\Support\WorkflowRejetCategorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Endpoints budget institutionnel (I-P3) + actions session SPA. */
class BudgetApiController extends Controller
{
    public function __construct(
        private readonly SuiviFinancierService $suivi,
        private readonly TableauBordService $tableauBord,
        private readonly WorkflowService $workflow,
        private readonly BudgetAuditService $audit,
        private readonly BudgetEnveloppeService $enveloppe,
        private readonly BudgetReferentielService $referentiel,
        private readonly BudgetNotificationService $notifications,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('budget.voir') || $request->user()->can('ligne_budgetaire.voir'), 403);

        $items = Budget::with('exercice', 'structure', 'responsable', 'workflowActif')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($terme) {
                    $inner->where('reference', 'like', $terme)->orWhere('libelle', 'like', $terme);
                });
            })
            ->orderByDesc('exercice_id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return BudgetResource::collection($items);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('create', Budget::class);

        $exercices = Exercice::query()
            ->whereDoesntHave('budgetInstitutionnel')
            ->orderByDesc('annee')
            ->get();

        $syntheses = $exercices->mapWithKeys(function (Exercice $exercice) {
            $depenses = LigneBudgetaire::query()
                ->where('exercice_id', $exercice->id)
                ->sum(DB::raw('COALESCE(montant_revise, montant_ouvert)'));
            $recettes = PrevisionRecette::query()
                ->where('exercice_id', $exercice->id)
                ->courante()
                ->valide()
                ->sum(DB::raw('COALESCE(montant_revise, montant_prevu)'));

            return [$exercice->id => [
                'annee' => $exercice->annee,
                'devise' => $exercice->devise ?: 'XAF',
                'statut' => $exercice->statut,
                'reference' => $this->enveloppe->genererReference($exercice),
                'lignes' => LigneBudgetaire::query()->where('exercice_id', $exercice->id)->count(),
                'depenses' => Montant::api($depenses),
                'recettes' => Montant::api($recettes),
                'solde' => Montant::api((float) $recettes - (float) $depenses),
            ]];
        });

        $premier = $exercices->first();

        return response()->json([
            'exercices' => $exercices->map->only(['id', 'annee', 'libelle', 'statut'])->values(),
            'syntheses' => $syntheses,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'type']),
            'responsables' => User::query()->where('actif', true)->orderBy('name')->get(['id', 'name']),
            'reference_suggestion' => $premier ? $this->enveloppe->genererReference($premier) : null,
        ]);
    }

    public function store(BudgetEnveloppeRequest $request): JsonResponse
    {
        try {
            $budget = $this->enveloppe->creer($request->validated(), (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'Enveloppe budgétaire créée.',
            'code' => 'cree',
            'budget' => (new BudgetResource($this->recharger($budget)))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Budget $budget): BudgetResource
    {
        abort_unless($request->user()->can('budget.voir') || $request->user()->can('ligne_budgetaire.voir'), 403);

        $budget->load([
            'exercice', 'structure', 'responsable', 'workflowActif.etapes.acteur',
        ]);

        $audit = $this->audit->executer($budget->exercice_id);
        $execution = $this->tableauBord->executionBudgetaire($budget->exercice_id);

        return (new BudgetResource($budget))->additional([
            'montant_calcule' => Montant::api($this->enveloppe->calculerMontantTotal($budget->exercice_id)),
            'execution' => Montant::champs($execution, [
                'ouvert', 'alloue', 'revise_delta', 'revise', 'engage', 'liquide', 'ordonnance',
                'paye', 'decaisse', 'disponible', 'reste_a_engager', 'reste_a_payer',
            ]),
            'audit' => [
                'anomalies_total' => $audit['anomalies_total'],
                'anomalies' => collect($audit['anomalies'] ?? [])->take(20)->values()->all(),
            ],
            'workflow_etapes' => $budget->workflowActif?->etapes->map(fn ($e) => [
                'id' => $e->id,
                'ordre' => $e->ordre,
                'libelle' => $e->libelle,
                'role' => $e->role,
                'statut' => $e->statut,
                'acteur' => $e->acteur?->only(['id', 'name']),
            ])->values() ?? [],
            'liens' => [
                'lignes' => $this->href('budget.index', ['exercice' => $budget->exercice_id]),
                'preparations' => $this->href('budget.enveloppe.preparations', $budget),
                'simulations' => $this->href('budget.enveloppe.simulations', $budget),
                'revisions' => $this->href('budget.enveloppe.revisions', $budget),
                'rapport' => $this->href('budget.enveloppe.rapport', $budget),
            ],
        ]);
    }

    public function update(BudgetEnveloppeRequest $request, Budget $budget): JsonResponse
    {
        $this->assertVersion($request, $budget);

        try {
            $budget = $this->enveloppe->mettreAJour($budget, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'Budget mis à jour.',
            'code' => 'modifie',
            'budget' => (new BudgetResource($this->recharger($budget)))->resolve($request),
        ]);
    }

    public function soumettre(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);
        abort_unless($request->user()->can('budget.modifier'), 403);

        $resultat = $this->idempotence->executer($request, 'budget.soumettre.'.$budget->id, function () use ($request, $budget) {
            $this->assertVersion($request, $budget);
            $actuel = $budget->fresh();
            if ($actuel && in_array($actuel->statut, ['en_validation', 'valide', 'adopte'], true)) {
                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Budget déjà soumis.',
                        'code' => 'deja_soumis',
                        'budget' => (new BudgetResource($this->recharger($actuel)))->resolve($request),
                    ],
                ];
            }

            if (! $this->audit->peutSoumettre($actuel->exercice_id)) {
                $rapport = $this->audit->executer($actuel->exercice_id);

                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Soumission impossible : '.$rapport['anomalies_total'].' anomalie(s).',
                        'code' => 'audit_bloquant',
                        'anomalies_total' => $rapport['anomalies_total'],
                    ],
                ];
            }

            if (in_array($actuel->statut, ['rejete', 'en_revision'], true)) {
                $actuel->increment('version');
            }

            $action = $actuel->statut === 'en_revision' ? 'resoumission_revision' : 'soumission';
            $this->referentiel->historiser($actuel, $action, $request->user()->id);
            $actuel->update(['montant_total' => $this->enveloppe->calculerMontantTotal($actuel->exercice_id)]);

            try {
                $instance = $this->workflow->demarrer($actuel, 'BUDGET', $request->user());
                $this->notifications->notifierSoumission($actuel->fresh(), $instance);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Budget soumis au circuit de validation.',
                    'code' => 'soumis',
                    'budget' => (new BudgetResource($this->recharger($actuel)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function agir(WorkflowDecisionRequest $request, Budget $budget): JsonResponse
    {
        $this->authorize('agir', $budget);
        $data = $request->validated();
        $instance = $budget->workflowActif;
        if (! $instance) {
            return response()->json(['message' => 'Aucun circuit actif.', 'code' => 'sans_circuit'], 422);
        }

        $resultat = $this->idempotence->executer($request, 'budget.decision.'.$budget->id, function () use ($request, $budget, $instance, $data) {
            $this->assertVersion($request, $budget);
            try {
                $this->workflow->traiterDecision(
                    $instance,
                    $request->user(),
                    $data['decision'],
                    $data['observation'] ?? null,
                    $data['categorie_rejet'] ?? WorkflowRejetCategorie::DOCUMENTAIRE,
                );
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            $actuel = $budget->fresh();
            if ($data['decision'] === 'rejeter' && $actuel) {
                $actuel->update([
                    'motif_rejet' => $data['observation'] ?? null,
                    'categorie_rejet' => $data['categorie_rejet'] ?? WorkflowRejetCategorie::DOCUMENTAIRE,
                ]);
            }

            $this->notifications->notifierDecision($budget->fresh(), $instance->fresh(), $data['decision'], $request->user());

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Décision enregistrée.',
                    'code' => 'decision',
                    'budget' => (new BudgetResource($this->recharger($budget)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function revision(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('reviser', $budget);
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:1000'],
        ]);

        $resultat = $this->idempotence->executer($request, 'budget.revision.'.$budget->id, function () use ($request, $budget, $data) {
            $this->assertVersion($request, $budget);
            try {
                $this->referentiel->demanderRevision($budget, (int) $request->user()->id, $data['motif']);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Budget ouvert en révision.',
                    'code' => 'en_revision',
                    'budget' => (new BudgetResource($this->recharger($budget)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function synthese(Request $request, Budget $budget): JsonResource
    {
        abort_unless($this->suivi->peutConsulter($request->user()), 403);

        return new JsonResource([
            'budget_id' => $budget->id,
            'exercice_id' => $budget->exercice_id,
            'synthese' => Montant::champs($this->suivi->hub($budget->exercice_id)['budget'], [
                'ouvert', 'alloue', 'revise_delta', 'revise', 'engage', 'liquide', 'ordonnance',
                'paye', 'decaisse', 'disponible', 'reste_a_engager', 'reste_a_payer',
            ]),
        ]);
    }

    private function recharger(Budget $budget): Budget
    {
        return $budget->fresh()->load('exercice', 'structure', 'responsable', 'workflowActif');
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
