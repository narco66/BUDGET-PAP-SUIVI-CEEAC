<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EncaissementRequest;
use App\Http\Requests\WorkflowDecisionSansRenvoiRequest;
use App\Http\Resources\EncaissementResource;
use App\Http\Resources\RecetteResource;
use App\Models\BudgetRubrique;
use App\Models\CategorieRecette;
use App\Models\Encaissement;
use App\Models\Recette;
use App\Services\Api\IdempotenceActionService;
use App\Services\Notifications\RecettesNotificationService;
use App\Services\Recettes\EncaissementService;
use App\Services\Recettes\GestionRecettesService;
use App\Services\WorkflowService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/** Endpoints recettes et encaissements (I-P3) + actions session SPA. */
class RecettesApiController extends Controller
{
    public function __construct(
        private readonly GestionRecettesService $gestion,
        private readonly EncaissementService $encaissements,
        private readonly WorkflowService $workflow,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function recettes(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Recette::class);

        $exerciceId = $request->integer('exercice') ?: null;

        $items = Recette::with(['exercice', 'categorie', 'etatMembre', 'rubrique'])
            ->withCount('encaissements')
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($terme) {
                    $inner->where('reference', 'like', $terme)->orWhere('observations', 'like', $terme);
                });
            })
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        $synthese = $exerciceId
            ? $this->gestion->syntheseExercice($exerciceId)
            : ['previsions' => 0, 'recettes' => 0, 'montant_prevu' => 0.0, 'montant_realise' => 0.0];

        $parCategorie = Recette::query()
            ->selectRaw('categorie_recette_id, SUM(montant_prevu) prevu, SUM(montant_realise) realise')
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->groupBy('categorie_recette_id')
            ->with('categorie')
            ->get()
            ->map(fn ($ligne) => [
                'categorie' => $ligne->categorie?->libelle,
                'montant_prevu' => Montant::api($ligne->prevu),
                'montant_realise' => Montant::api($ligne->realise),
            ]);

        return RecetteResource::collection($items)->additional([
            'synthese' => [
                'previsions' => (int) $synthese['previsions'],
                'recettes' => (int) $synthese['recettes'],
                'montant_prevu' => Montant::api($synthese['montant_prevu']),
                'montant_realise' => Montant::api($synthese['montant_realise']),
            ],
            'par_categorie' => $parCategorie,
            'categories' => CategorieRecette::actif()->orderBy('ordre')->get(['id', 'code', 'libelle']),
        ]);
    }

    public function showRecette(Request $request, Recette $recette): RecetteResource
    {
        $this->authorize('view', $recette);
        $recette->load(['exercice', 'categorie', 'etatMembre', 'rubrique', 'encaissements.workflowActif']);

        return (new RecetteResource($recette))->additional([
            'rubriques' => BudgetRubrique::orderBy('code')->limit(300)->get(['id', 'code', 'intitule']),
        ]);
    }

    public function ventiler(Request $request, Recette $recette): JsonResponse
    {
        $this->authorize('update', $recette);
        $data = $request->validate([
            'rubrique_id' => ['required', 'exists:budget_rubriques,id'],
        ]);

        $recette->update(['rubrique_id' => $data['rubrique_id']]);

        return response()->json([
            'message' => 'Recette ventilée sur la rubrique budgétaire.',
            'code' => 'ventilee',
            'recette' => (new RecetteResource($this->rechargerRecette($recette)))->resolve($request),
        ]);
    }

    public function encaissements(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Encaissement::class);

        $items = Encaissement::with('recette', 'workflowActif')
            ->withCount('pieces')
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('recette'), fn ($q) => $q->where('recette_id', $request->integer('recette')))
            ->when($request->filled('exercice'), function ($q) use ($request) {
                $q->whereHas('recette', fn ($r) => $r->where('exercice_id', $request->integer('exercice')));
            })
            ->orderByDesc('date_encaissement')
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return EncaissementResource::collection($items);
    }

    public function optionsEncaissement(Request $request): JsonResponse
    {
        $this->authorize('create', Encaissement::class);

        $recettes = Recette::query()
            ->with(['categorie', 'etatMembre'])
            ->whereIn('statut', ['prevue', 'partiellement_realisee'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->orderBy('reference')
            ->limit(300)
            ->get();

        return response()->json([
            'modes_paiement' => Encaissement::MODES_PAIEMENT,
            'recettes' => $recettes->map(fn (Recette $r) => [
                'id' => $r->id,
                'reference' => $r->reference,
                'statut' => $r->statut,
                'montant_prevu' => Montant::api($r->montant_prevu),
                'montant_realise' => Montant::api($r->montant_realise),
                'rubrique_id' => $r->rubrique_id,
                'categorie' => $r->categorie?->libelle,
            ])->values(),
        ]);
    }

    public function storeEncaissement(EncaissementRequest $request): JsonResponse
    {
        $this->authorize('create', Encaissement::class);

        try {
            $encaissement = $this->encaissements->saisir($request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'Encaissement saisi.',
            'code' => 'saisi',
            'encaissement' => (new EncaissementResource($this->rechargerEncaissement($encaissement)))->resolve($request),
        ], 201);
    }

    public function showEncaissement(Request $request, Encaissement $encaissement): EncaissementResource
    {
        $this->authorize('view', $encaissement);
        $encaissement->load(['recette.exercice', 'recette.rubrique', 'workflowActif.etapes.acteur', 'pieces']);

        return new EncaissementResource($encaissement);
    }

    public function soumettreEncaissement(Request $request, Encaissement $encaissement): JsonResponse
    {
        $this->authorize('view', $encaissement);
        abort_unless($request->user()->can('recette.encaissement'), 403);

        $resultat = $this->idempotence->executer($request, 'encaissement.soumettre.'.$encaissement->id, function () use ($request, $encaissement) {
            $this->assertVersion($request, $encaissement);
            $actuel = $encaissement->fresh();
            if ($actuel && in_array($actuel->statut, ['en_validation', 'valide', 'rapproche'], true)) {
                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Encaissement déjà soumis.',
                        'code' => 'deja_soumis',
                        'encaissement' => (new EncaissementResource($this->rechargerEncaissement($actuel)))->resolve($request),
                    ],
                ];
            }

            try {
                $this->encaissements->assertPretPourValidation($actuel);
                $this->workflow->demarrer($actuel, 'RECETTES', $request->user());
                app(RecettesNotificationService::class)->notifierEncaissementSoumis($actuel->fresh());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Encaissement soumis au circuit de validation.',
                    'code' => 'soumis',
                    'encaissement' => (new EncaissementResource($this->rechargerEncaissement($actuel)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function agirEncaissement(WorkflowDecisionSansRenvoiRequest $request, Encaissement $encaissement): JsonResponse
    {
        $this->authorize('agir', $encaissement);
        $data = $request->validated();
        $instance = $encaissement->workflowActif;
        if (! $instance) {
            return response()->json(['message' => 'Aucun circuit actif.', 'code' => 'sans_circuit'], 422);
        }

        $resultat = $this->idempotence->executer($request, 'encaissement.decision.'.$encaissement->id, function () use ($request, $encaissement, $instance, $data) {
            $this->assertVersion($request, $encaissement);
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
                    'encaissement' => (new EncaissementResource($this->rechargerEncaissement($encaissement)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function rapprocherEncaissement(Request $request, Encaissement $encaissement): JsonResponse
    {
        $this->authorize('rapprocher', $encaissement);
        $data = $request->validate([
            'date_rapprochement' => ['required', 'date'],
            'reference_bancaire' => ['nullable', 'string', 'max:100'],
        ]);

        $resultat = $this->idempotence->executer($request, 'encaissement.rapprocher.'.$encaissement->id, function () use ($request, $encaissement, $data) {
            $this->assertVersion($request, $encaissement);
            try {
                $this->encaissements->rapprocher($encaissement->fresh(), $data, $request->user());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Encaissement rapproché.',
                    'code' => 'rapproche',
                    'encaissement' => (new EncaissementResource($this->rechargerEncaissement($encaissement)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function annulerEncaissement(Request $request, Encaissement $encaissement): JsonResponse
    {
        $this->authorize('annuler', $encaissement);

        try {
            $this->encaissements->annuler($encaissement);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'Encaissement annulé.',
            'code' => 'annule',
            'encaissement' => (new EncaissementResource($this->rechargerEncaissement($encaissement)))->resolve($request),
        ]);
    }

    private function rechargerRecette(Recette $recette): Recette
    {
        return $recette->fresh()->load(['exercice', 'categorie', 'etatMembre', 'rubrique'])->loadCount('encaissements');
    }

    private function rechargerEncaissement(Encaissement $encaissement): Encaissement
    {
        return $encaissement->fresh()->load(['recette', 'workflowActif'])->loadCount('pieces');
    }

    private function assertVersion(Request $request, Encaissement $encaissement): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        if ($attendu !== $encaissement->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'L’encaissement a été modifié. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }
}
