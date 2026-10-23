<?php



namespace App\Http\Controllers;



use App\Models\Budget;

use App\Models\BudgetRevision;

use App\Models\LigneBudgetaire;

use App\Models\Strategie\ProgrammationPluriannuelle;

use App\Services\Budget\BudgetPluriannuelService;

use App\Services\Budget\BudgetRevisionService;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\View\View;

use RuntimeException;



/** Rectificatifs, virements et lien pluriannuel (phase B4). */

class BudgetRevisionController extends Controller

{

    public function __construct(

        private readonly BudgetRevisionService $revisions,

        private readonly BudgetPluriannuelService $pluriannuel,

    ) {

    }



    public function index(Budget $budget): View

    {

        $this->authorize('rectifier', $budget);



        $historique = $this->revisions->historique($budget);

        $rapprochement = $this->pluriannuel->comparer($budget);

        $glissement = $this->pluriannuel->glissement($budget);

        $lignes = LigneBudgetaire::where('exercice_id', $budget->exercice_id)

            ->orderBy('code')

            ->get(['id', 'code', 'intitule', 'montant_ouvert', 'montant_revise', 'montant_engage']);

        $pluriannuelles = ProgrammationPluriannuelle::with('planStrategique')

            ->orderByDesc('annee_debut')

            ->get();



        return view('budget.enveloppe.revisions', compact(

            'budget', 'historique', 'rapprochement', 'glissement', 'lignes', 'pluriannuelles'

        ));

    }



    public function rectificatif(Request $request, Budget $budget): RedirectResponse

    {

        $this->authorize('rectifier', $budget);



        $data = $request->validate([

            'ligne_budgetaire_id' => ['required', 'exists:lignes_budgetaires,id'],

            'montant_apres' => ['required', 'numeric', 'min:0'],

            'motif' => ['required', 'string', 'max:1000'],

        ]);



        try {

            $revision = $this->revisions->creerRectificatif(

                $budget,

                (int) $data['ligne_budgetaire_id'],

                (float) $data['montant_apres'],

                $data['motif'],

                (int) auth()->id(),

            );

            $this->revisions->valider($revision, (int) auth()->id());

        } catch (RuntimeException $e) {

            return back()->with('erreur', $e->getMessage())->withInput();

        }



        return back()->with('succes', 'Rectificatif enregistré et appliqué.');

    }



    public function virement(Request $request, Budget $budget): RedirectResponse

    {

        $this->authorize('rectifier', $budget);



        $data = $request->validate([

            'ligne_source_id' => ['required', 'exists:lignes_budgetaires,id'],

            'ligne_destination_id' => ['required', 'different:ligne_source_id', 'exists:lignes_budgetaires,id'],

            'montant' => ['required', 'numeric', 'min:1'],

            'motif' => ['required', 'string', 'max:1000'],

        ]);



        try {

            $revision = $this->revisions->creerVirement(

                $budget,

                (int) $data['ligne_source_id'],

                (int) $data['ligne_destination_id'],

                (float) $data['montant'],

                $data['motif'],

                (int) auth()->id(),

            );

            $this->revisions->valider($revision, (int) auth()->id());

        } catch (RuntimeException $e) {

            return back()->with('erreur', $e->getMessage())->withInput();

        }



        return back()->with('succes', 'Virement enregistré et appliqué.');

    }



    public function valider(Budget $budget, BudgetRevision $revision): RedirectResponse

    {

        $this->authorize('rectifier', $budget);

        abort_unless((int) $revision->budget_id === (int) $budget->id, 404);



        try {

            $this->revisions->valider($revision, (int) auth()->id());

        } catch (RuntimeException $e) {

            return back()->with('erreur', $e->getMessage());

        }



        return back()->with('succes', 'Révision validée.');

    }



    public function lierProgrammation(Request $request, Budget $budget): RedirectResponse

    {

        $this->authorize('rectifier', $budget);



        $data = $request->validate([

            'programmation_pluriannuelle_id' => ['required', 'exists:programmations_pluriannuelles,id'],

            'programmation_annuelle_id' => ['nullable', 'exists:programmations_annuelles,id'],

        ]);



        try {

            $this->pluriannuel->lier(

                $budget,

                (int) $data['programmation_pluriannuelle_id'],

                isset($data['programmation_annuelle_id']) ? (int) $data['programmation_annuelle_id'] : null,

                (int) auth()->id(),

            );

        } catch (RuntimeException $e) {

            return back()->with('erreur', $e->getMessage());

        }



        return back()->with('succes', 'Programmation pluriannuelle rattachée au budget.');

    }

}

