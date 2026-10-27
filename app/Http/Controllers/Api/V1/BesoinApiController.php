<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpressionBesoinRequest;
use App\Http\Requests\WorkflowDecisionRequest;
use App\Http\Resources\ExpressionBesoinResource;
use App\Models\Activite;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Services\AccessScopeService;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\ExerciceService;
use App\Services\Depense\BesoinCircuitService;
use App\Services\Depense\BesoinVersEngagementService;
use App\Services\Depense\HeritagePapService;
use App\Services\DocumentPdf;
use App\Services\Notifications\DepenseNotificationService;
use App\Services\WorkflowService;
use App\Support\Numero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BesoinApiController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly AccessScopeService $scopes,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExpressionBesoin::class);

        $query = $this->scopes->filtrerStructuresMetier(
            ExpressionBesoin::with('structure', 'demandeur', 'exercice', 'activite', 'ligneBudgetaire', 'lignes', 'engagement', 'workflowActif')->latest(),
            $request->user(),
        );

        $query->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice));
        $query->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut));
        $query->when($request->filled('categorie'), fn ($q) => $q->where('categorie', $request->categorie));
        $query->when($request->filled('q'), function ($q) use ($request) {
            $terme = '%'.$request->string('q').'%';
            $q->where(function ($inner) use ($terme) {
                $inner->where('numero', 'like', $terme)->orWhere('objet', 'like', $terme);
            });
        });

        return ExpressionBesoinResource::collection(
            $query->paginate(min(100, max(1, (int) $request->integer('per_page', 20))))
        );
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('create', ExpressionBesoin::class);
        $exerciceId = $request->integer('exercice');

        $activites = $this->scopes->filtrerStructuresMetier(
            Activite::with(['ligneBudgetaire', 'sousProduit.produit.axe.pilier', 'produit.axe.pilier', 'structure'])->orderBy('code'),
            $request->user(),
        )->when($exerciceId > 0, function ($q) use ($exerciceId) {
            $q->whereNotNull('ligne_budgetaire_id')
                ->whereHas('ligneBudgetaire', fn ($l) => $l->where('exercice_id', $exerciceId));
        })->limit(500)->get();

        $heritage = app(HeritagePapService::class);
        $lignes = $this->scopes->filtrerStructures(
            LigneBudgetaire::query()->orderBy('code'),
            $request->user(),
        )->when($exerciceId > 0, fn ($q) => $q->where('exercice_id', $exerciceId))->limit(500)->get();

        return response()->json([
            'priorites' => ExpressionBesoin::PRIORITES,
            'categories' => ['pap' => 'PAP', 'hors_pap' => 'Hors PAP'],
            'activites' => $activites->map(fn (Activite $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'intitule' => $a->intitule,
                'ligne_budgetaire_id' => $a->ligne_budgetaire_id,
                'ligne_budgetaire' => $a->ligneBudgetaire?->code,
                'heritage' => $heritage->capturer($a->id),
            ])->values(),
            'lignes_budgetaires' => $lignes->map(fn (LigneBudgetaire $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'intitule' => $l->intitule,
                'disponible' => \App\Support\Montant::api($l->disponible),
            ])->values(),
        ]);
    }

    public function store(ExpressionBesoinRequest $request): JsonResponse
    {
        $this->authorize('create', ExpressionBesoin::class);
        try {
            app(ExerciceService::class)->assertExerciceOuvert((int) $request->exercice_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'credits_ou_transition'], 422);
        }
        $annee = Exercice::find($request->exercice_id)?->annee ?? (int) date('Y');

        $donnees = $request->safe()->except(['lignes']);
        $lignes = $request->lignesNormalisees();
        $montant = $request->montantCalcule();

        if (($donnees['categorie'] ?? 'pap') === 'pap' && empty($donnees['ligne_budgetaire_id']) && ! empty($donnees['activite_id'])) {
            $donnees['ligne_budgetaire_id'] = Activite::find($donnees['activite_id'])?->ligne_budgetaire_id;
        }
        if (empty($donnees['structure_id'])) {
            $donnees['structure_id'] = $request->user()->structure_id;
        }

        $besoin = ExpressionBesoin::create([
            ...$donnees,
            'categorie' => $donnees['categorie'] ?? 'pap',
            'montant_estime' => $montant,
            'numero' => Numero::suivant('expressions_besoin', 'EB', $annee),
            'demandeur_id' => $request->user()->id,
            'statut' => 'brouillon',
        ]);

        foreach ($lignes as $ligne) {
            $besoin->lignes()->create($ligne);
        }

        if ($besoin->estPap()) {
            app(HeritagePapService::class)->figer($besoin);
        }

        $besoin->load('structure', 'demandeur', 'activite', 'ligneBudgetaire', 'lignes', 'engagement', 'workflowActif');

        return response()->json([
            'message' => 'Expression du besoin créée.',
            'code' => 'cree',
            'besoin' => (new ExpressionBesoinResource($besoin))->resolve($request),
        ], 201);
    }

    public function update(ExpressionBesoinRequest $request, ExpressionBesoin $besoin): JsonResponse
    {
        $this->authorize('update', $besoin);

        $donnees = $request->safe()->except(['lignes']);
        $lignes = $request->lignesNormalisees();
        $montant = $request->montantCalcule();
        if ($besoin->statut !== 'brouillon') {
            $donnees['categorie'] = $besoin->categorie ?? 'pap';
        }
        if (($donnees['categorie'] ?? $besoin->categorie ?? 'pap') === 'pap' && empty($donnees['ligne_budgetaire_id']) && ! empty($donnees['activite_id'])) {
            $donnees['ligne_budgetaire_id'] = Activite::find($donnees['activite_id'])?->ligne_budgetaire_id;
        }

        $besoin->update([
            ...$donnees,
            'montant_estime' => $montant,
        ]);
        if ($lignes !== []) {
            $besoin->lignes()->delete();
            foreach ($lignes as $ligne) {
                $besoin->lignes()->create($ligne);
            }
        }
        if ($besoin->fresh()->estPap()) {
            app(HeritagePapService::class)->figer($besoin->fresh());
        }

        return response()->json([
            'message' => 'Expression du besoin mise à jour.',
            'code' => 'mis_a_jour',
            'besoin' => (new ExpressionBesoinResource($this->recharger($besoin)))->resolve($request),
        ]);
    }

    public function show(Request $request, ExpressionBesoin $besoin): ExpressionBesoinResource
    {
        $this->authorize('view', $besoin);
        $besoin->load('structure', 'demandeur', 'activite', 'tache', 'exercice', 'ligneBudgetaire', 'lignes', 'engagement', 'workflowActif.etapes.acteur');

        return new ExpressionBesoinResource($besoin);
    }

    public function soumettre(Request $request, ExpressionBesoin $besoin): JsonResponse
    {
        abort_unless($request->user()->can('besoin.modifier'), 403);
        abort_unless($this->scopes->dansPerimetre($request->user(), $besoin->structure_id), 403);

        $resultat = $this->idempotence->executer($request, 'besoin.soumettre.'.$besoin->id, function () use ($request, $besoin) {
            $this->assertVersion($request, $besoin);
            try {
                $actuel = $besoin->fresh();
                app(ExerciceService::class)->assertExerciceOuvert((int) $actuel->exercice_id);
                if ($actuel->estPap() && ! $actuel->activite_id) {
                    return ['status' => 422, 'body' => ['message' => 'Une activité PAP valide est obligatoire.', 'code' => 'pap_incomplet']];
                }
                if (! $actuel->estPap() && blank($actuel->justification)) {
                    return ['status' => 422, 'body' => ['message' => 'La justification métier est obligatoire hors PAP.', 'code' => 'hors_pap_incomplet']];
                }
                app(HeritagePapService::class)->figer($actuel);
                if ($actuel->enCorrection() && $actuel->workflowActif) {
                    // Dossier corrigé après un retour : le circuit reprend au niveau qui l'avait renvoyé.
                    $this->workflow->reprendreApresCorrection($actuel->fresh(), $request->user());
                } else {
                    $circuit = app(BesoinCircuitService::class)->code($actuel->fresh());
                    $this->workflow->demarrer($actuel->fresh(), $circuit, $request->user());
                }
                app(DepenseNotificationService::class)->notifierBesoinSoumis($actuel->fresh());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Besoin soumis pour validation.',
                    'code' => 'soumis',
                    'besoin' => (new ExpressionBesoinResource($this->recharger($besoin)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function agir(WorkflowDecisionRequest $request, ExpressionBesoin $besoin): JsonResponse
    {
        $this->authorize('agir', $besoin);
        $data = $request->validated();
        $instance = $besoin->workflowActif;
        if (! $instance) {
            return response()->json(['message' => 'Aucun circuit actif.', 'code' => 'sans_circuit'], 422);
        }

        $resultat = $this->idempotence->executer($request, 'besoin.decision.'.$besoin->id, function () use ($request, $besoin, $instance, $data) {
            $this->assertVersion($request, $besoin);
            try {
                $this->workflow->traiterDecision(
                    $instance,
                    $request->user(),
                    $data['decision'],
                    $data['observation'] ?? null,
                    $data['categorie_rejet'] ?? null,
                );
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Décision enregistrée.',
                    'code' => 'decision',
                    'besoin' => (new ExpressionBesoinResource($this->recharger($besoin)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function convertir(Request $request, ExpressionBesoin $besoin): JsonResponse
    {
        $this->authorize('view', $besoin);
        abort_unless($request->user()->can('engagement.creer'), 403);

        $resultat = $this->idempotence->executer($request, 'besoin.convertir.'.$besoin->id, function () use ($request, $besoin) {
            $actuel = $besoin->fresh();
            if ($actuel && $actuel->statut === 'converti') {
                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Besoin déjà converti.',
                        'code' => 'converti',
                        'besoin' => (new ExpressionBesoinResource($this->recharger($actuel)))->resolve($request),
                    ],
                ];
            }

            abort_unless($actuel && in_array($actuel->statut, ['valide', 'converti'], true), 422, 'Le besoin doit être validé.');
            $engagement = app(BesoinVersEngagementService::class)
                ->assurerDepuisBesoinApprouve($actuel, $request->user());
            $actuel->update(['statut' => 'converti']);

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Engagement unique généré depuis le besoin approuvé.',
                    'code' => 'converti',
                    'engagement_id' => $engagement->id,
                    'besoin' => (new ExpressionBesoinResource($this->recharger($actuel)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    private function recharger(ExpressionBesoin $besoin): ExpressionBesoin
    {
        return $besoin->fresh()->load('structure', 'demandeur', 'activite', 'ligneBudgetaire', 'lignes', 'engagement', 'workflowActif');
    }

    public function pdf(ExpressionBesoin $besoin, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $besoin);

        return $pdf->besoin($besoin)->stream("besoin_{$besoin->numero}.pdf");
    }

    private function assertVersion(Request $request, ExpressionBesoin $besoin): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        if ($attendu !== $besoin->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'Le besoin a été modifié. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }
}
