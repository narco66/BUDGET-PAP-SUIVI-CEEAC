<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pap\PapRequest;
use App\Http\Requests\Pap\PapRevisionRequest;
use App\Http\Requests\WorkflowDecisionRequest;
use App\Http\Resources\PapResource;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Structure;
use App\Models\User;
use App\Services\Api\IdempotenceActionService;
use App\Services\Pap\PapAuditService;
use App\Services\Pap\PapEnveloppeService;
use App\Services\Pap\PapNotificationService;
use App\Services\Pap\PapPerformanceService;
use App\Services\Pap\PapPilotageService;
use App\Services\Pap\PapReferentielService;
use App\Services\WorkflowService;
use App\Support\PapMontants;
use App\Support\WorkflowRejetCategorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/** Endpoints PAP (I-P3) + actions session SPA. */
class PapApiController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly PapAuditService $audit,
        private readonly PapPerformanceService $performance,
        private readonly PapPilotageService $pilotage,
        private readonly PapReferentielService $referentiel,
        private readonly PapEnveloppeService $enveloppe,
        private readonly PapNotificationService $notifications,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pap::class);

        $items = Pap::with('exercice', 'structure', 'responsable', 'workflowActif')
            ->withCount('piliers')
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

        return PapResource::collection($items);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('create', Pap::class);

        $exercices = Exercice::query()
            ->whereDoesntHave('pap')
            ->orderByDesc('annee')
            ->get(['id', 'annee', 'libelle', 'statut']);

        $premier = $exercices->first();

        return response()->json([
            'exercices' => $exercices,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'type']),
            'responsables' => User::query()->where('actif', true)->orderBy('name')->get(['id', 'name']),
            'reference_suggestion' => $premier ? $this->enveloppe->genererReference($premier) : null,
        ]);
    }

    public function store(PapRequest $request): JsonResponse
    {
        $this->authorize('create', Pap::class);

        try {
            $pap = $this->enveloppe->creer($request->validated(), (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'PAP créé.',
            'code' => 'cree',
            'pap' => (new PapResource($this->recharger($pap)))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Pap $pap): PapResource
    {
        $this->authorize('view', $pap);
        $pap->load(['exercice', 'structure', 'responsable', 'workflowActif.etapes.acteur']);
        $pap->loadCount('piliers');

        $synthese = PapMontants::ligne($this->performance->executionPap($pap->exercice_id));
        $audit = $this->audit->executer($pap->exercice_id);
        $audit['synthese'] = PapMontants::ligne($audit['synthese'] ?? []);
        $audit['consolidation_structures'] = PapMontants::lignes($audit['consolidation_structures'] ?? []);

        return (new PapResource($pap))->additional([
            'synthese' => $synthese,
            'audit' => [
                'anomalies_critiques' => $audit['anomalies_critiques'],
                'anomalies_total' => $audit['anomalies_total'],
                'anomalies' => collect($audit['anomalies'])->take(20)->values()->all(),
            ],
            'piliers' => PapMontants::lignes($this->pilotage->performancePiliers($pap->exercice_id)->take(12)),
        ]);
    }

    public function update(PapRequest $request, Pap $pap): JsonResponse
    {
        $this->authorize('update', $pap);
        $this->assertVersion($request, $pap);

        try {
            $pap = $this->enveloppe->mettreAJour($pap, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'PAP mis à jour.',
            'code' => 'modifie',
            'pap' => (new PapResource($this->recharger($pap)))->resolve($request),
        ]);
    }

    public function soumettre(Request $request, Pap $pap): JsonResponse
    {
        $this->authorize('view', $pap);
        abort_unless($request->user()->can('pap.modifier'), 403);

        $resultat = $this->idempotence->executer($request, 'pap.soumettre.'.$pap->id, function () use ($request, $pap) {
            $this->assertVersion($request, $pap);
            $actuel = $pap->fresh();
            if ($actuel && in_array($actuel->statut, ['en_validation', 'valide', 'publie'], true)) {
                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'PAP déjà soumis.',
                        'code' => 'deja_soumis',
                        'pap' => (new PapResource($this->recharger($actuel)))->resolve($request),
                    ],
                ];
            }

            if (! $this->audit->peutSoumettre($actuel->exercice_id)) {
                $rapport = $this->audit->executer($actuel->exercice_id);

                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Soumission impossible : '.$rapport['anomalies_critiques'].' anomalie(s) critique(s).',
                        'code' => 'audit_bloquant',
                        'anomalies_critiques' => $rapport['anomalies_critiques'],
                    ],
                ];
            }

            if (in_array($actuel->statut, ['rejete', 'en_revision'], true)) {
                $actuel->increment('version');
            }

            $action = $actuel->statut === 'en_revision' ? 'resoumission_revision' : 'soumission';
            $this->referentiel->historiser($actuel, $action, $request->user()->id);

            try {
                $instance = $this->workflow->demarrer($actuel, 'PAP', $request->user());
                $this->notifications->notifierSoumission($actuel->fresh(), $instance);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'PAP soumis au circuit de validation.',
                    'code' => 'soumis',
                    'pap' => (new PapResource($this->recharger($actuel)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function agir(WorkflowDecisionRequest $request, Pap $pap): JsonResponse
    {
        $this->authorize('agir', $pap);
        $data = $request->validated();
        $instance = $pap->workflowActif;
        if (! $instance) {
            return response()->json(['message' => 'Aucun circuit actif.', 'code' => 'sans_circuit'], 422);
        }

        $resultat = $this->idempotence->executer($request, 'pap.decision.'.$pap->id, function () use ($request, $pap, $instance, $data) {
            $this->assertVersion($request, $pap);
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

            $actuel = $pap->fresh();
            if ($data['decision'] === 'rejeter' && $actuel) {
                $actuel->update([
                    'motif_rejet' => $data['observation'] ?? null,
                    'categorie_rejet' => $data['categorie_rejet'] ?? WorkflowRejetCategorie::DOCUMENTAIRE,
                ]);
            }

            $this->notifications->notifierDecision($pap->fresh(), $instance->fresh(), $data['decision'], $request->user());

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Décision enregistrée.',
                    'code' => 'decision',
                    'pap' => (new PapResource($this->recharger($pap)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function revision(PapRevisionRequest $request, Pap $pap): JsonResponse
    {
        $this->authorize('reviser', $pap);

        $resultat = $this->idempotence->executer($request, 'pap.revision.'.$pap->id, function () use ($request, $pap) {
            $this->assertVersion($request, $pap);
            try {
                $this->referentiel->demanderRevision($pap, (int) $request->user()->id, $request->validated('motif'));
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'PAP ouvert en révision.',
                    'code' => 'en_revision',
                    'pap' => (new PapResource($this->recharger($pap)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function pilotage(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Pap::class);

        $exercice = $this->resoudreExercice($request);
        if ($exercice === null) {
            return response()->json(['message' => 'Exercice introuvable.', 'code' => 'introuvable'], 404);
        }

        $pap = Pap::with('exercice', 'structure', 'responsable', 'workflowActif')
            ->withCount('piliers')
            ->firstWhere('exercice_id', $exercice->id);

        $tableau = $this->pilotage->tableau($exercice->id);
        $alertes = $tableau['alertes'];

        return response()->json([
            'exercice' => $exercice->only(['id', 'annee', 'libelle', 'statut']),
            'pap' => $pap ? (new PapResource($pap))->resolve($request) : null,
            'synthese' => PapMontants::ligne($tableau['synthese']),
            'indicateurs' => PapMontants::ligne($tableau['indicateurs']),
            'piliers' => PapMontants::lignes($tableau['piliers']),
            'produits' => PapMontants::lignes(collect($tableau['produits'])->take(20)),
            'alertes' => [
                'total_alertes' => $alertes['total_alertes'],
                'en_retard' => PapMontants::lignes(collect($alertes['en_retard'])->take(20)),
                'surconsommation' => PapMontants::lignes(collect($alertes['surconsommation'])->take(20)),
            ],
            'audit' => [
                'anomalies_critiques' => $tableau['audit']['anomalies_critiques'] ?? 0,
                'anomalies_total' => $tableau['audit']['anomalies_total'] ?? 0,
            ],
        ]);
    }

    private function recharger(Pap $pap): Pap
    {
        return $pap->fresh()->load('exercice', 'structure', 'responsable', 'workflowActif')->loadCount('piliers');
    }

    private function assertVersion(Request $request, Pap $pap): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        if ($attendu !== $pap->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'Le PAP a été modifié. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }

    private function resoudreExercice(Request $request): ?Exercice
    {
        if ($request->filled('exercice')) {
            $opt = $request->input('exercice');

            return Exercice::where('id', $opt)->orWhere('annee', $opt)->first();
        }

        return Exercice::query()->where('statut', 'ouvert')->orderByDesc('annee')->first()
            ?? Exercice::query()->orderByDesc('annee')->first();
    }
}
