<?php



namespace App\Http\Controllers\Api\V1;



use App\Http\Controllers\Controller;

use App\Http\Resources\LigneBudgetaireResource;

use App\Models\LigneBudgetaire;

use App\Services\Budget\StructureBudgetService;

use App\Services\Budget\StructureBudgetValidationService;

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

    ) {

    }



    public function synthese(Request $request): JsonResource

    {

        abort_unless($request->user()->can('structure_budget.voir'), 403);



        $exerciceId = (int) ($request->integer('exercice') ?: 0);

        abort_unless($exerciceId > 0, 422, 'Paramètre exercice requis.');



        return new JsonResource([

            'exercice_id' => $exerciceId,

            'synthese' => $this->service->synthese($exerciceId),

            'par_departement' => $this->service->totauxParType($exerciceId, 'departement')->values(),

            'par_direction' => $this->service->totauxParType($exerciceId, 'direction')->values(),

            'par_service' => $this->service->totauxParType($exerciceId, 'service')->values(),

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

            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->string('q').'%'))

            ->orderBy('code')

            ->paginate(min(100, max(1, (int) $request->integer('per_page', 50))));



        return LigneBudgetaireResource::collection($items);

    }



    public function showLigne(Request $request, LigneBudgetaire $ligne): LigneBudgetaireResource

    {

        abort_unless($request->user()->can('structure_budget.voir'), 403);



        $ligne->load(['rubrique', 'structure', 'activite', 'validePar', 'exercice']);



        return new LigneBudgetaireResource($ligne);

    }



    public function soumettre(Request $request, LigneBudgetaire $ligne): JsonResponse

    {

        abort_unless($request->user()->can('structure_budget.modifier'), 403);



        try {

            $this->validation->soumettre($ligne, $request->user());

        } catch (RuntimeException $e) {

            return response()->json(['message' => $e->getMessage()], 422);

        }



        return response()->json(['message' => 'Ligne soumise.', 'ligne' => new LigneBudgetaireResource($ligne->fresh())]);

    }



    public function valider(Request $request, LigneBudgetaire $ligne): JsonResponse

    {

        abort_unless($request->user()->can('structure_budget.valider'), 403);



        try {

            $this->validation->valider($ligne, $request->user());

        } catch (RuntimeException $e) {

            return response()->json(['message' => $e->getMessage()], 422);

        }



        return response()->json(['message' => 'Ligne validée.', 'ligne' => new LigneBudgetaireResource($ligne->fresh())]);

    }



    public function rejeter(Request $request, LigneBudgetaire $ligne): JsonResponse

    {

        abort_unless($request->user()->can('structure_budget.rejeter') || $request->user()->can('structure_budget.valider'), 403);



        $data = $request->validate(['motif_rejet' => ['required', 'string', 'max:1000']]);



        try {

            $this->validation->rejeter($ligne, $request->user(), $data['motif_rejet']);

        } catch (RuntimeException $e) {

            return response()->json(['message' => $e->getMessage()], 422);

        }



        return response()->json(['message' => 'Ligne rejetée.', 'ligne' => new LigneBudgetaireResource($ligne->fresh())]);

    }



    /** @param array<int,array<string,mixed>> $noeuds */

    private function serialiserArbre(array $noeuds): array

    {

        return array_map(function (array $n) {

            $s = $n['structure'];



            return [

                'id' => $s->id,

                'code' => $s->code,

                'libelle' => $s->libelle,

                'sigle' => $s->sigle,

                'type' => $s->type,

                'propre_ouvert' => $n['propre_ouvert'],

                'nb_propre' => $n['nb_propre'],

                'total_ouvert' => $n['total_ouvert'],

                'total_engage' => $n['total_engage'],

                'total_paye' => $n['total_paye'],

                'nb_total' => $n['nb_total'],

                'enfants' => $this->serialiserArbre($n['enfants']),

            ];

        }, $noeuds);

    }

}


