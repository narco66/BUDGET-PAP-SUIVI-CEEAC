<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditWorkflow;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Services\Workflow\WorkflowCorbeilleService;
use App\Services\Workflow\WorkflowDashboardService;
use App\Services\WorkflowService;
use App\Support\WorkflowObjetUrls;
use App\Support\WorkflowRejetCategorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** Endpoints REST Workflow institutionnel (phase W-P3). */
class WorkflowApiController extends Controller
{
    public function __construct(
        private readonly WorkflowCorbeilleService $corbeille,
        private readonly WorkflowDashboardService $dashboard,
        private readonly WorkflowService $workflow,
    ) {}

    public function hub(Request $request): JsonResponse
    {
        $this->assertWorkflow($request);
        $user = $request->user();
        $jours = min(365, max(7, (int) $request->integer('jours', 90)));
        $data = $this->dashboard->tableau($jours);
        $taches = $this->corbeille->tachesPour($user);

        return response()->json([
            'periode_jours' => $data['periode_jours'],
            'kpi' => $data['kpi'],
            'corbeille' => $taches->count(),
            'peut_valider' => $this->peut($request, 'workflow.validate'),
            'peut_auditer' => $this->peut($request, 'workflow.audit'),
            'peut_admin' => $this->peut($request, 'workflow.admin'),
            'par_circuit' => collect($data['par_circuit'])->map(fn ($w) => [
                'code' => $w->code,
                'libelle' => $w->libelle,
                'en_cours' => (int) $w->en_cours_count,
                'valides' => (int) $w->valides_count,
                'rejetes' => (int) $w->rejetes_count,
            ])->values()->all(),
            'dernieres_actions' => collect($data['dernieres_actions'])->map(fn ($a) => [
                'action' => $a->action,
                'circuit' => $a->workflow_code,
                'etape' => $a->etape_ordre,
                'acteur' => $a->acteur?->name,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values()->all(),
            'href_audit' => $this->href('workflow.audit'),
            'href_admin' => $this->peut($request, 'workflow.admin') ? $this->href('admin.workflows.index') : null,
            'href_export' => $this->peut($request, 'workflow.audit') ? $this->href('workflow.dashboard.export') : null,
        ]);
    }

    public function audit(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'workflow.audit'), 403);

        $journaux = AuditWorkflow::query()
            ->with('acteur')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('workflow'), fn ($q) => $q->where('workflow_code', $request->workflow))
            ->latest('created_at')
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 40))));

        return response()->json([
            'data' => $journaux->getCollection()->map(fn (AuditWorkflow $j) => [
                'id' => $j->id,
                'created_at' => $j->created_at?->toIso8601String(),
                'action' => $j->action,
                'circuit' => $j->workflow_code,
                'etape' => $j->etape_ordre,
                'acteur' => $j->acteur?->name ?? 'Système',
                'objet' => $j->objet_type
                    ? class_basename($j->objet_type).' #'.$j->objet_id
                    : null,
                'instance_id' => $j->workflow_instance_id,
                'observation' => $j->observation,
                'categorie_rejet' => $j->categorie_rejet,
            ])->values()->all(),
            'meta' => [
                'total' => $journaux->total(),
                'current_page' => $journaux->currentPage(),
                'last_page' => $journaux->lastPage(),
            ],
            'filtres' => [
                'actions' => AuditWorkflow::query()->distinct()->orderBy('action')->pluck('action')->values()->all(),
                'circuits' => AuditWorkflow::query()->distinct()->orderBy('workflow_code')->pluck('workflow_code')->values()->all(),
            ],
            'href_export' => $this->href('workflow.dashboard.export'),
        ]);
    }

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
        abort_unless($this->workflow->peutAgir($instance, $request->user()) || $this->peut($request, 'workflow.audit'), 403);

        $instance->load(['workflow', 'etapes.acteur', 'objet', 'version']);

        return new JsonResource($this->formatInstance($instance, $request->user()));
    }

    public function decision(Request $request, WorkflowInstance $instance): JsonResponse
    {
        $this->assertWorkflow($request);
        abort_unless($this->peut($request, 'workflow.validate'), 403);

        // Motif et catégorie exigés par la procédure : plus de catégorie « technique » imposée par défaut.
        $data = $request->validate([
            'decision' => ['required', 'in:valider,rejeter,renvoyer'],
            'observation' => ['nullable', 'required_if:decision,rejeter,renvoyer', 'string', 'max:2000'],
            'categorie_rejet' => ['nullable', 'required_if:decision,rejeter', 'string', 'in:'.implode(',', WorkflowRejetCategorie::values())],
            'etape_attendue_id' => ['nullable', 'integer'],
        ]);

        $etapeAttendue = isset($data['etape_attendue_id']) ? (int) $data['etape_attendue_id'] : null;
        // Commande idempotente (P04) : un rejeu de la même clé renvoie la même réponse sans nouvelle transition.
        $resultat = app(\App\Services\Api\IdempotenceActionService::class)->executer(
            $request,
            'workflow.decision.'.$instance->id,
            function () use ($request, $instance, $data, $etapeAttendue) {
                if (! $this->workflow->peutAgir($instance->fresh(), $request->user())) {
                    $statut = $instance->fresh()->statut !== 'en_cours' || ($etapeAttendue && ! $this->etapeEnAttente($instance, $etapeAttendue)) ? 409 : 403;

                    return $this->refus($request, $instance, $data['decision'], $statut,
                        $statut === 409 ? 'Ce dossier a déjà été traité ou a changé d\'étape.' : 'Vous n\'êtes pas habilité à traiter l\'étape courante.');
                }
                try {
                    // Mêmes contrôles et effets que l'écran du module (RIB, valideur, notifications…).
                    $objet = $instance->objet;
                    if ($objet && method_exists($objet, 'workflowActif') && $objet->workflowActif?->id === $instance->id) {
                        app(\App\Services\Workflow\DecisionMetierService::class)->decider($objet, $request->user(), $data, $etapeAttendue);
                    } else {
                        $this->workflow->traiterDecision($instance, $request->user(), $data['decision'], $data['observation'] ?? null, $data['categorie_rejet'] ?? null, $etapeAttendue);
                    }
                } catch (RuntimeException $e) {
                    $conflit = str_contains($e->getMessage(), 'déjà été traitée') || str_contains($e->getMessage(), 'Aucune étape en attente');

                    return $this->refus($request, $instance, $data['decision'], $conflit ? 409 : 422, $e->getMessage());
                }

                return ['status' => 200, 'body' => [
                    'message' => 'Décision enregistrée.',
                    'data' => $this->formatInstance($instance->fresh(['workflow', 'etapes.acteur', 'objet', 'version']), $request->user()),
                ]];
            },
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    private function etapeEnAttente(WorkflowInstance $instance, int $etapeId): bool
    {
        return $instance->etapes()->whereKey($etapeId)->where('statut', 'en_attente')->exists();
    }

    /** Refus tracé au journal central (§ 20 : refus d'action, revalidation des droits à l'instant de décision). */
    private function refus(Request $request, WorkflowInstance $instance, string $decision, int $statut, string $message): array
    {
        \App\Support\AuditLogger::evenement('workflow_decision_refusee', $instance, [
            'module' => 'WORKFLOW',
            'resultat' => 'refus',
            'motif' => $message,
            'workflow_instance_id' => $instance->id,
            'apres' => ['decision' => $decision, 'statut_http' => $statut, 'etape_courante' => $instance->etape_courante],
        ]);

        return ['status' => $statut, 'body' => ['message' => $message]];
    }

    private function assertWorkflow(Request $request): void
    {
        abort_unless($this->peut($request, 'workflow.view'), 403);
    }

    private function peut(Request $request, string $ability): bool
    {
        try {
            return (bool) $request->user()?->can($ability);
        } catch (PermissionDoesNotExist) {
            return false;
        }
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
                'id' => $e->id,
                'ordre' => $e->ordre,
                'libelle' => $e->libelle,
                'role' => $e->role,
                'statut' => $e->statut,
                'acteur_id' => $e->acteur_id,
                'decision_le' => $e->decision_le?->toIso8601String(),
                'echeance_le' => $e->echeance_le?->toIso8601String(),
            ])->values(),
            // Historique des décisions (§ 18 « history ») : acteur, motif, catégorie, empreinte de visa.
            'historique' => $instance->etapes->whereNotNull('decision_le')->sortBy('decision_le')->map(fn ($e) => [
                'etape' => $e->ordre,
                'libelle' => $e->libelle,
                'decision' => $e->statut,
                'acteur' => $e->acteur?->name,
                'observation' => $e->observation,
                'categorie_rejet' => $e->categorie_rejet,
                'decision_le' => $e->decision_le?->toIso8601String(),
                'visa' => $e->hash_visa ? substr($e->hash_visa, 0, 16) : null,
            ])->values(),
            'version_publiee_le' => $instance->version?->published_at?->toIso8601String(),
            'peut_agir' => $this->workflow->peutAgir($instance, $user),
        ];
    }

    private function href(string $name, mixed ...$params): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, $params, false);
    }
}
