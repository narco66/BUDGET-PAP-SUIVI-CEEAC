<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExerciceRequest;
use App\Models\Exercice;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\ExerciceService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Gestion des exercices budgétaires — session SPA. GET /exercices (PAT) inchangé. */
class ExerciceApiController extends Controller
{
    public function __construct(
        private readonly ExerciceService $exercices,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('exercice.voir'), 403);

        $liste = Exercice::query()
            ->with('cloturePar:id,name')
            ->withCount(['lignesBudgetaires', 'piliers'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->string('q').'%')
                ->orWhere('libelle', 'like', '%'.$request->string('q').'%')
                ->orWhere('annee', 'like', '%'.$request->string('q').'%')))
            ->orderByDesc('annee')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 15))));

        $user = $request->user();

        return response()->json([
            'statuts' => Exercice::STATUTS,
            'actions' => [
                'creer' => $user?->can('exercice.creer') ?? false,
            ],
            'data' => collect($liste->items())->map(fn (Exercice $ex) => $this->serialiser($ex, $user, false))->values(),
            'meta' => [
                'total' => $liste->total(),
                'page' => $liste->currentPage(),
                'per_page' => $liste->perPage(),
            ],
        ]);
    }

    public function show(Request $request, Exercice $exercice): JsonResponse
    {
        abort_unless($request->user()?->can('exercice.voir'), 403);
        $exercice->load('cloturePar:id,name', 'pap:id,exercice_id,statut,reference');
        $exercice->loadCount(['lignesBudgetaires', 'piliers', 'contributions']);

        return response()->json([
            'exercice' => $this->serialiser($exercice, $request->user(), true),
        ]);
    }

    public function store(ExerciceRequest $request): JsonResponse
    {
        $this->authorize('create', Exercice::class);
        $data = $request->validated();
        $resultat = $this->idempotence->executer(
            $request,
            'exercice.creer.'.$data['code'],
            function () use ($request, $data) {
                $exercice = Exercice::create([
                    ...$data,
                    'statut' => 'brouillon',
                    'est_actif' => false,
                ]);

                return [
                    'status' => 201,
                    'body' => [
                        'message' => 'Exercice budgétaire créé.',
                        'exercice' => $this->serialiser($exercice->fresh(), $request->user(), true),
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(ExerciceRequest $request, Exercice $exercice): JsonResponse
    {
        $this->authorize('update', $exercice);
        $this->assertVersion($request, $exercice);
        $exercice->update($request->validated());

        return response()->json([
            'message' => 'Exercice mis à jour.',
            'exercice' => $this->serialiser($exercice->fresh(), $request->user(), true),
        ]);
    }

    public function destroy(Request $request, Exercice $exercice): JsonResponse
    {
        $this->authorize('delete', $exercice);
        $this->assertVersion($request, $exercice);
        if ($exercice->lignesBudgetaires()->exists() || $exercice->piliers()->exists() || $exercice->pap()->exists()) {
            return response()->json([
                'message' => 'Suppression impossible : des données sont rattachées à cet exercice.',
                'code' => 'transition_invalide',
            ], 422);
        }
        $exercice->delete();

        return response()->json(['message' => 'Exercice supprimé.']);
    }

    public function ouvrir(Request $request, Exercice $exercice): JsonResponse
    {
        $this->authorize('ouvrir', $exercice);

        return $this->transition($request, $exercice, 'ouvrir', function () use ($request, $exercice) {
            return $this->exercices->ouvrir($exercice, $request->user());
        }, 'Exercice ouvert.');
    }

    public function cloturer(Request $request, Exercice $exercice): JsonResponse
    {
        $this->authorize('cloturer', $exercice);

        return $this->transition($request, $exercice, 'cloturer', function () use ($request, $exercice) {
            return $this->exercices->cloturer($exercice, $request->user());
        }, 'Exercice clôturé. Toute écriture budgétaire est désormais bloquée.');
    }

    public function activer(Request $request, Exercice $exercice): JsonResponse
    {
        $this->authorize('activer', $exercice);

        return $this->transition($request, $exercice, 'activer', function () use ($request, $exercice) {
            return $this->exercices->definirActif($exercice, $request->user());
        }, 'Exercice défini comme exercice de référence.');
    }

    private function transition(Request $request, Exercice $exercice, string $action, callable $travail, string $message): JsonResponse
    {
        $this->assertVersion($request, $exercice);
        $resultat = $this->idempotence->executer(
            $request,
            'exercice.'.$action.'.'.$exercice->id,
            function () use ($request, $travail, $message) {
                try {
                    $exercice = $travail();
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 200,
                    'body' => [
                        'message' => $message,
                        'exercice' => $this->serialiser($exercice, $request->user(), true),
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    /** @return array<string, mixed> */
    private function serialiser(Exercice $ex, mixed $user, bool $detail): array
    {
        $base = [
            'id' => $ex->id,
            'code' => $ex->code,
            'annee' => $ex->annee,
            'libelle' => $ex->libelle,
            'statut' => $ex->statut,
            'est_actif' => (bool) $ex->est_actif,
            'devise' => $ex->devise,
            'date_debut' => $ex->date_debut?->toDateString(),
            'date_fin' => $ex->date_fin?->toDateString(),
            'lignes_budgetaires_count' => (int) ($ex->lignes_budgetaires_count ?? 0),
            'piliers_count' => (int) ($ex->piliers_count ?? 0),
            'etag' => $ex->updated_at?->toIso8601String(),
            'actions' => [
                'modifier' => $user?->can('update', $ex) ?? false,
                'supprimer' => $user?->can('delete', $ex) ?? false,
                'ouvrir' => ($user?->can('ouvrir', $ex) ?? false) && $ex->statut === 'brouillon',
                'cloturer' => $user?->can('cloturer', $ex) ?? false,
                'activer' => ($user?->can('activer', $ex) ?? false) && ! $ex->est_actif,
            ],
        ];

        if (! $detail) {
            return $base;
        }

        return $base + [
            'taux_change_usd' => $ex->taux_change_usd !== null ? Montant::api($ex->taux_change_usd, 6) : null,
            'date_cloture' => $ex->date_cloture?->toIso8601String(),
            'cloture_par' => $ex->cloturePar?->only(['id', 'name']),
            'contributions_count' => (int) ($ex->contributions_count ?? 0),
            'pap' => $ex->pap ? [
                'id' => $ex->pap->id,
                'statut' => $ex->pap->statut,
                'reference' => $ex->pap->reference ?? null,
            ] : null,
        ];
    }

    private function assertVersion(Request $request, Exercice $exercice): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }
        if ($attendu !== $exercice->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'L’exercice a été modifié. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }
}
