<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LigneBudgetaireResource;
use App\Models\LigneBudgetaire;
use App\Services\AccessScopeService;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\StructureBudgetService;
use App\Services\Budget\StructureBudgetValidationService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

/** API REST — Module Structure du Budget (Lot 6). */
class StructureBudgetApiController extends Controller
{
    public function __construct(
        private readonly StructureBudgetService $service,
        private readonly StructureBudgetValidationService $validation,
        private readonly IdempotenceActionService $idempotence,
        private readonly AccessScopeService $scopes,
    ) {}

    public function synthese(Request $request): JsonResource
    {
        abort_unless($request->user()->can('structure_budget.voir'), 403);

        $exerciceId = (int) ($request->integer('exercice') ?: 0);
        abort_unless($exerciceId > 0, 422, 'Paramètre exercice requis.');

        $montants = ['ouvert', 'engage', 'paye'];

        return new JsonResource([
            'exercice_id' => $exerciceId,
            'synthese' => Montant::champs($this->service->synthese($exerciceId), $montants),
            'par_departement' => $this->service->totauxParType($exerciceId, 'departement')->map(
                fn ($row) => Montant::champs((array) $row, $montants)
            )->values(),
            'par_direction' => $this->service->totauxParType($exerciceId, 'direction')->map(
                fn ($row) => Montant::champs((array) $row, $montants)
            )->values(),
            'par_service' => $this->service->totauxParType($exerciceId, 'service')->map(
                fn ($row) => Montant::champs((array) $row, $montants)
            )->values(),
        ]);
    }

    public function arbre(Request $request): JsonResource
    {
        abort_unless($request->user()->can('structure_budget.voir'), 403);

        $exerciceId = (int) ($request->integer('exercice') ?: 0);
        abort_unless($exerciceId > 0, 422, 'Paramètre exercice requis.');

        return new JsonResource([
            'exercice_id' => $exerciceId,
            'noeuds' => $this->serialiserArbre($this->service->arbre($exerciceId)),
        ]);
    }

    public function lignes(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('structure_budget.voir'), 403);

        $items = LigneBudgetaire::with(['rubrique', 'structure', 'validePar'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
            ->when($request->filled('structure'), fn ($q) => $q->where('structure_id', $request->structure))
            ->when($request->filled('statut_validation'), fn ($q) => $q->where('statut_validation', $request->statut_validation))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($inner) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $inner->where('code', 'like', $terme)->orWhere('intitule', 'like', $terme);
            }))
            ->orderBy('code')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 50))));

        return LigneBudgetaireResource::collection($items);
    }

    public function showLigne(Request $request, LigneBudgetaire $ligne): LigneBudgetaireResource
    {
        abort_unless($request->user()->can('structureBudget.viewLigne', $ligne), 403);

        $ligne->load(['rubrique', 'structure', 'activite', 'validePar', 'exercice']);

        return new LigneBudgetaireResource($ligne);
    }

    public function soumettre(Request $request, LigneBudgetaire $ligne): JsonResponse
    {
        $this->assertPermission($request, $ligne, 'structure_budget.modifier');

        $resultat = $this->idempotence->executer($request, 'structure-budget.soumettre.'.$ligne->id, function () use ($request, $ligne) {
            $this->assertVersion($request, $ligne);
            try {
                $maj = $this->validation->soumettre($ligne, $request->user());
                $maj->load(['rubrique', 'structure', 'validePar']);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => ['message' => 'Ligne soumise.', 'code' => 'soumise', 'ligne' => (new LigneBudgetaireResource($maj))->resolve($request)],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function valider(Request $request, LigneBudgetaire $ligne): JsonResponse
    {
        $this->assertPermission($request, $ligne, 'structure_budget.valider');

        $resultat = $this->idempotence->executer($request, 'structure-budget.valider.'.$ligne->id, function () use ($request, $ligne) {
            $this->assertVersion($request, $ligne);
            try {
                $maj = $this->validation->valider($ligne, $request->user());
                $maj->load(['rubrique', 'structure', 'validePar']);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => ['message' => 'Ligne validée.', 'code' => 'validee', 'ligne' => (new LigneBudgetaireResource($maj))->resolve($request)],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function rejeter(Request $request, LigneBudgetaire $ligne): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('structure_budget.rejeter') || $user->can('structure_budget.valider'), 403);
        abort_unless($this->scopes->dansPerimetre($user, $ligne->structure_id), 403);

        $data = $request->validate(['motif_rejet' => ['required', 'string', 'max:1000']]);

        $resultat = $this->idempotence->executer($request, 'structure-budget.rejeter.'.$ligne->id, function () use ($request, $ligne, $data) {
            $this->assertVersion($request, $ligne);
            try {
                $maj = $this->validation->rejeter($ligne, $request->user(), $data['motif_rejet']);
                $maj->load(['rubrique', 'structure', 'validePar']);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => ['message' => 'Ligne rejetée.', 'code' => 'rejetee', 'ligne' => (new LigneBudgetaireResource($maj))->resolve($request)],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    private function assertPermission(Request $request, LigneBudgetaire $ligne, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
        abort_unless($this->scopes->dansPerimetre($request->user(), $ligne->structure_id), 403);
    }

    private function assertVersion(Request $request, LigneBudgetaire $ligne): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        $actuel = $ligne->updated_at?->toIso8601String();
        if ($attendu !== $actuel) {
            abort(response()->json([
                'message' => 'La ligne a été modifiée par un autre utilisateur. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }

    /** @param  array<int, array<string, mixed>>  $noeuds */
    private function serialiserArbre(array $noeuds): array
    {
        $montants = ['propre_ouvert', 'total_ouvert', 'total_engage', 'total_paye'];

        return array_map(function (array $n) use ($montants) {
            $s = $n['structure'];
            $ligne = Montant::champs($n, $montants);

            return [
                'id' => $s->id,
                'code' => $s->code,
                'libelle' => $s->libelle,
                'sigle' => $s->sigle,
                'type' => $s->type,
                'propre_ouvert' => $ligne['propre_ouvert'],
                'nb_propre' => $n['nb_propre'],
                'total_ouvert' => $ligne['total_ouvert'],
                'total_engage' => $ligne['total_engage'],
                'total_paye' => $ligne['total_paye'],
                'nb_total' => $n['nb_total'],
                'enfants' => $this->serialiserArbre($n['enfants']),
            ];
        }, $noeuds);
    }
}
