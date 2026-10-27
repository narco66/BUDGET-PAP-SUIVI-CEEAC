<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkflowTacheResource;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Models\WorkflowTacheEvenement;
use App\Services\Api\IdempotenceActionService;
use App\Services\Workflow\TacheActionService;
use App\Services\Workflow\TacheConflitException;
use App\Services\Workflow\TacheQueryService;
use App\Support\WorkflowDecisionRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/** API « Mes Tâches » (§ 16 du référentiel) : files de travail, détail, actions et décisions. */
class TachesApiController extends Controller
{
    public function __construct(
        private readonly TacheQueryService $requetes,
        private readonly TacheActionService $actions,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request, string $vue = 'mes'): AnonymousResourceCollection
    {
        $vue = ['my' => 'mes', 'unit' => 'unite', 'delegated' => 'deleguees', 'history' => 'traitees'][$vue] ?? $vue;
        abort_unless(in_array($vue, TacheQueryService::VUES, true), 404);
        abort_if($vue === 'unite' && ! $this->requetes->peutVoirUnite($request->user()), 403);
        $filtres = $request->only(['module', 'etape', 'statut', 'decision', 'priorite', 'echeance', 'structure_id', 'du', 'au', 'q', 'affectation', 'montant_min', 'montant_max', 'tri', 'sens']);
        if ($vue === 'traitees') {
            $filtres += ['tri' => 'termine', 'sens' => 'desc'];
        }
        $page = $this->requetes->lister($request->user(), $vue, $filtres, min(100, max(5, $request->integer('par_page', 20))), max(1, $request->integer('page', 1)));

        return WorkflowTacheResource::collection($page);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->requetes->statistiques($request->user(), $request->input('depuis'))]);
    }

    public function supervision(Request $request): JsonResponse
    {
        abort_unless($this->requetes->peutSuperviser($request->user()), 403);

        return response()->json(['data' => $this->requetes->supervision($request->user(), $request->input('depuis'))]);
    }

    public function show(Request $request, WorkflowTache $tache): WorkflowTacheResource
    {
        abort_unless($this->requetes->peutConsulter($tache, $request->user()), 403);

        return new WorkflowTacheResource($tache->load(['instance.workflow', 'etape', 'objet', 'structure', 'assigne', 'terminePar']));
    }

    public function actionsDisponibles(Request $request, WorkflowTache $tache): JsonResponse
    {
        abort_unless($this->requetes->peutConsulter($tache, $request->user()), 403);

        return response()->json(['data' => $this->actions->actionsDisponibles($tache, $request->user())]);
    }

    public function historique(Request $request, WorkflowTache $tache): JsonResponse
    {
        abort_unless($this->requetes->peutConsulter($tache, $request->user()), 403);
        $taches = $tache->tachesLiees()->pluck('reference', 'id');
        $evenements = WorkflowTacheEvenement::query()->with(['user', 'pourCompteDe'])
            ->whereIn('workflow_tache_id', $taches->keys())->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (WorkflowTacheEvenement $e) => [
                'tache' => $taches[$e->workflow_tache_id] ?? null,
                'type' => $e->type,
                'libelle' => WorkflowTacheEvenement::TYPES[$e->type] ?? $e->type,
                'acteur' => $e->user?->name,
                'pour_compte_de' => $e->pourCompteDe?->name,
                'commentaire' => $e->commentaire,
                'donnees' => $e->donnees,
                'le' => $e->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $evenements]);
    }

    public function prendreEnCharge(Request $request, WorkflowTache $tache): JsonResponse
    {
        return $this->executer($request, $tache, 'claim', fn () => $this->actions->prendreEnCharge($tache, $request->user(), $this->version($request)));
    }

    public function liberer(Request $request, WorkflowTache $tache): JsonResponse
    {
        return $this->executer($request, $tache, 'release', fn () => $this->actions->liberer($tache, $request->user(), $this->version($request)));
    }

    public function reaffecter(Request $request, WorkflowTache $tache): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'motif' => ['required', 'string', 'max:1000']]);

        return $this->executer($request, $tache, 'reassign', fn () => $this->actions->reaffecter($tache, $request->user(), User::findOrFail($data['user_id']), $data['motif'], $this->version($request)));
    }

    public function suspendre(Request $request, WorkflowTache $tache): JsonResponse
    {
        $data = $request->validate(['motif' => ['required', 'string', 'max:1000']]);

        return $this->executer($request, $tache, 'suspend', fn () => $this->actions->suspendre($tache, $request->user(), $data['motif'], $this->version($request)));
    }

    public function reprendre(Request $request, WorkflowTache $tache): JsonResponse
    {
        return $this->executer($request, $tache, 'resume', fn () => $this->actions->reprendre($tache, $request->user(), $this->version($request)));
    }

    public function prioriser(Request $request, WorkflowTache $tache): JsonResponse
    {
        $data = $request->validate(['priorite' => ['required', 'in:'.implode(',', array_keys(WorkflowTache::PRIORITES))], 'motif' => ['required', 'string', 'max:1000']]);

        return $this->executer($request, $tache, 'priority', fn () => $this->actions->prioriser($tache, $request->user(), $data['priorite'], $data['motif'], $this->version($request)));
    }

    public function commenter(Request $request, WorkflowTache $tache): JsonResponse
    {
        $data = $request->validate(['commentaire' => ['required', 'string', 'max:2000']]);

        return $this->executer($request, $tache, 'comment', fn () => $this->actions->commenter($tache, $request->user(), $data['commentaire']), 201);
    }

    public function decider(Request $request, WorkflowTache $tache): JsonResponse
    {
        $data = $request->validate(WorkflowDecisionRules::regles(avecRenvoi: true));

        return $this->executer($request, $tache, 'decision', fn () => $this->actions->decider($tache, $request->user(), $data, $this->version($request)));
    }

    /** Version attendue : en-tête If-Match ou champ « version » (concurrence optimiste). */
    private function version(Request $request): ?int
    {
        $v = $request->header('If-Match') ?? $request->input('version');

        return $v === null || $v === '' ? null : (int) trim((string) $v, '"W/');
    }

    private function executer(Request $request, WorkflowTache $tache, string $action, callable $travail, int $statutOk = 200): JsonResponse
    {
        abort_unless($this->requetes->peutConsulter($tache, $request->user()), 403);

        $resultat = $this->idempotence->executer($request, "tache.{$action}.{$tache->id}", function () use ($travail, $request, $statutOk) {
            try {
                $t = $travail();
            } catch (TacheConflitException $e) {
                return ['status' => 409, 'body' => ['message' => $e->getMessage(), 'code' => 'conflict']];
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'action_refusee']];
            }

            return ['status' => $statutOk, 'body' => [
                'message' => 'Action enregistrée.',
                'data' => (new WorkflowTacheResource($t->fresh(['instance.workflow', 'etape', 'objet', 'structure', 'assigne', 'terminePar'])))->resolve($request),
            ]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }
}
