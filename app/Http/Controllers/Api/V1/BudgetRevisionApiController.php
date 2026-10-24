<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetRevision;
use App\Models\LigneBudgetaire;
use App\Models\Strategie\ProgrammationPluriannuelle;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\BudgetPluriannuelService;
use App\Services\Budget\BudgetRevisionService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Rectificatifs, virements et lien pluriannuel (B4) — session SPA. */
class BudgetRevisionApiController extends Controller
{
    public function __construct(
        private readonly BudgetRevisionService $revisions,
        private readonly BudgetPluriannuelService $pluriannuel,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function show(Request $request, Budget $budget): JsonResponse
    {
        abort_unless($request->user()->can('budget.voir') || $request->user()->can('ligne_budgetaire.voir'), 403);

        $user = $request->user();
        $budget->load(['exercice', 'structure', 'responsable', 'workflowActif', 'programmationPluriannuelle', 'programmationAnnuelle']);
        $rapprochement = Montant::champs($this->pluriannuel->comparer($budget), [
            'montant_budget', 'enveloppe_pluriannuelle', 'ecart_absolu', 'ecart_pct',
        ]);
        if (isset($rapprochement['programmation_annuelle']['ressources_prevues'])) {
            $rapprochement['programmation_annuelle']['ressources_prevues'] = Montant::api(
                $rapprochement['programmation_annuelle']['ressources_prevues']
            );
        }

        $lignes = LigneBudgetaire::where('exercice_id', $budget->exercice_id)
            ->orderBy('code')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 50))));

        return response()->json([
            'budget' => (new BudgetResource($budget))->resolve($request),
            'rapprochement' => $rapprochement,
            'glissement' => array_map(fn (array $g) => Montant::champs($g, ['enveloppe_estimee']), $this->pluriannuel->glissement($budget)),
            'lignes' => collect($lignes->items())->map(fn (LigneBudgetaire $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'intitule' => $l->intitule,
                'montant_ouvert' => Montant::api($l->montant_ouvert),
                'montant_revise' => $l->montant_revise !== null ? Montant::api($l->montant_revise) : null,
                'montant_alloue' => Montant::api($l->montant_alloue),
                'montant_engage' => Montant::api($l->montant_engage),
                'disponible' => Montant::api($l->disponible),
            ])->values(),
            'meta' => [
                'total' => $lignes->total(),
                'page' => $lignes->currentPage(),
                'per_page' => $lignes->perPage(),
            ],
            'historique' => $this->revisions->historique($budget)->take(30)->map(fn (BudgetRevision $r) => $this->serialiser($r))->values(),
            'pluriannuelles' => ProgrammationPluriannuelle::query()
                ->with('planStrategique:id,reference')
                ->orderByDesc('annee_debut')
                ->get(['id', 'reference', 'annee_debut', 'annee_fin', 'plan_strategique_id'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'annee_debut' => $p->annee_debut,
                    'annee_fin' => $p->annee_fin,
                    'plan' => $p->planStrategique?->reference,
                ])->values(),
            'actions' => [
                'rectifier' => $user?->can('rectifier', $budget) ?? false,
            ],
        ]);
    }

    public function rectificatif(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('rectifier', $budget);
        $data = $request->validate([
            'ligne_budgetaire_id' => ['required', 'exists:lignes_budgetaires,id'],
            'montant_apres' => ['required', 'numeric', 'min:0'],
            'motif' => ['required', 'string', 'max:1000'],
        ]);

        $resultat = $this->idempotence->executer(
            $request,
            'budget.rev.rectificatif.'.$budget->id.'.'.$data['ligne_budgetaire_id'].'.'.$data['montant_apres'],
            function () use ($request, $budget, $data) {
                $this->assertVersion($request, $budget);
                try {
                    $revision = $this->revisions->creerRectificatif(
                        $budget,
                        (int) $data['ligne_budgetaire_id'],
                        (float) $data['montant_apres'],
                        $data['motif'],
                        (int) $request->user()->id,
                    );
                    $revision = $this->revisions->valider($revision, (int) $request->user()->id);
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Rectificatif enregistré et appliqué.',
                        'code' => 'valide',
                        'revision' => $this->serialiser($revision->load(['mouvements.ligneBudgetaire', 'createur', 'validateur'])),
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function virement(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('rectifier', $budget);
        $data = $request->validate([
            'ligne_source_id' => ['required', 'exists:lignes_budgetaires,id'],
            'ligne_destination_id' => ['required', 'different:ligne_source_id', 'exists:lignes_budgetaires,id'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'motif' => ['required', 'string', 'max:1000'],
        ]);

        $resultat = $this->idempotence->executer(
            $request,
            'budget.rev.virement.'.$budget->id.'.'.$data['ligne_source_id'].'.'.$data['ligne_destination_id'].'.'.$data['montant'],
            function () use ($request, $budget, $data) {
                $this->assertVersion($request, $budget);
                try {
                    $revision = $this->revisions->creerVirement(
                        $budget,
                        (int) $data['ligne_source_id'],
                        (int) $data['ligne_destination_id'],
                        (float) $data['montant'],
                        $data['motif'],
                        (int) $request->user()->id,
                    );
                    $revision = $this->revisions->valider($revision, (int) $request->user()->id);
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Virement enregistré et appliqué.',
                        'code' => 'valide',
                        'revision' => $this->serialiser($revision->load(['mouvements.ligneBudgetaire', 'createur', 'validateur'])),
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function lierProgrammation(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('rectifier', $budget);
        $data = $request->validate([
            'programmation_pluriannuelle_id' => ['required', 'exists:programmations_pluriannuelles,id'],
            'programmation_annuelle_id' => ['nullable', 'exists:programmations_annuelles,id'],
        ]);

        $resultat = $this->idempotence->executer(
            $request,
            'budget.rev.lier.'.$budget->id.'.'.$data['programmation_pluriannuelle_id'],
            function () use ($request, $budget, $data) {
                $this->assertVersion($request, $budget);
                try {
                    $this->pluriannuel->lier(
                        $budget,
                        (int) $data['programmation_pluriannuelle_id'],
                        isset($data['programmation_annuelle_id']) ? (int) $data['programmation_annuelle_id'] : null,
                        (int) $request->user()->id,
                    );
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Programmation pluriannuelle rattachée au budget.',
                        'code' => 'lie',
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    /** @return array<string, mixed> */
    private function serialiser(BudgetRevision $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'type' => $r->type,
            'motif' => $r->motif,
            'statut' => $r->statut,
            'montant_total' => Montant::api($r->montant_total),
            'valide_le' => $r->valide_le?->toIso8601String(),
            'createur' => $r->createur?->only(['id', 'name']),
            'validateur' => $r->validateur?->only(['id', 'name']),
            'mouvements' => $r->mouvements->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'ligne' => $m->ligneBudgetaire?->only(['id', 'code']),
                'montant_avant' => Montant::api($m->montant_avant),
                'montant_apres' => Montant::api($m->montant_apres),
                'delta' => Montant::api($m->delta),
            ])->values(),
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
}
