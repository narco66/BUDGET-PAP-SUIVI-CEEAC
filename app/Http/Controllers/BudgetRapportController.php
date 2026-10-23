<?php



namespace App\Http\Controllers;



use App\Models\Budget;

use App\Services\Budget\BudgetReportService;

use Illuminate\View\View;



/** Rapport de synthèse du cycle budgétaire (phase B5). */

class BudgetRapportController extends Controller

{

    public function __construct(private readonly BudgetReportService $rapport)

    {

    }



    public function show(Budget $budget): View

    {

        $this->authorize('view', $budget);



        $synthese = $this->rapport->synthese($budget->exercice_id);



        return view('budget.enveloppe.rapport', compact('budget', 'synthese'));

    }



    public function exercice(): View

    {

        $this->authorize('viewAny', Budget::class);



        $synthese = $this->rapport->synthese();

        $budget = isset($synthese['enveloppe']['id'])

            ? Budget::find($synthese['enveloppe']['id'])

            : null;



        return view('budget.enveloppe.rapport', compact('budget', 'synthese'));

    }

}

