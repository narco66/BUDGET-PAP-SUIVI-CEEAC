<?php



namespace App\Http\Controllers;



use App\Models\Budget;

use App\Models\BudgetSimulation;

use App\Services\Budget\BudgetConsolidationService;

use App\Services\Budget\BudgetSimulationService;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\View\View;

use RuntimeException;



/** Consolidation persistée et scénarios what-if (phase B3). */

class BudgetSimulationController extends Controller

{

    public function __construct(

        private readonly BudgetSimulationService $simulation,

        private readonly BudgetConsolidationService $consolidation,

    ) {

    }



    public function index(Budget $budget, Request $request): View

    {

        $this->authorize('simuler', $budget);



        $simulations = $this->simulation->simulations($budget);

        $consolidations = $this->consolidation->historique($budget);

        $comparaison = null;



        $ids = array_filter(array_map('intval', (array) $request->input('comparer', [])));

        if (count($ids) >= 2) {

            try {

                $comparaison = $this->simulation->comparer($budget, $ids);

            } catch (RuntimeException) {

                // affichage sans comparaison si scénarios invalides

            }

        }



        return view('budget.enveloppe.simulations', compact(

            'budget', 'simulations', 'consolidations', 'comparaison', 'ids'

        ));

    }



    public function store(Request $request, Budget $budget): RedirectResponse

    {

        $this->authorize('simuler', $budget);



        $data = $request->validate([

            'libelle' => ['required', 'string', 'max:200'],

            'hypothese' => ['nullable', 'string', 'max:2000'],

            'type' => ['required', 'in:global_pct,structure_pct,enveloppe_plafond,ligne_ajustements'],

            'taux' => ['nullable', 'numeric'],

            'structure_id' => ['nullable', 'exists:structures,id'],

            'plafond' => ['nullable', 'numeric', 'min:0'],

        ]);



        $parametres = match ($data['type']) {

            'global_pct' => ['taux' => (float) ($data['taux'] ?? 0)],

            'structure_pct' => [

                'structure_id' => (int) ($data['structure_id'] ?? 0),

                'taux' => (float) ($data['taux'] ?? 0),

            ],

            'enveloppe_plafond' => ['plafond' => (float) ($data['plafond'] ?? 0)],

            default => [],

        };



        try {

            $sim = $this->simulation->creer($budget, [

                'libelle' => $data['libelle'],

                'hypothese' => $data['hypothese'] ?? null,

                'type' => $data['type'],

                'parametres' => $parametres,

            ], (int) auth()->id());

            $this->simulation->calculer($sim, (int) auth()->id());

        } catch (RuntimeException $e) {

            return back()->with('erreur', $e->getMessage())->withInput();

        }



        return back()->with('succes', 'Scénario créé et calculé.');

    }



    public function calculer(Budget $budget, BudgetSimulation $simulation): RedirectResponse

    {

        $this->authorize('simuler', $budget);

        abort_unless((int) $simulation->budget_id === (int) $budget->id, 404);



        try {

            $this->simulation->calculer($simulation, (int) auth()->id());

        } catch (RuntimeException $e) {

            return back()->with('erreur', $e->getMessage());

        }



        return back()->with('succes', 'Scénario recalculé.');

    }



    public function retenir(Budget $budget, BudgetSimulation $simulation): RedirectResponse

    {

        $this->authorize('simuler', $budget);

        abort_unless((int) $simulation->budget_id === (int) $budget->id, 404);



        try {

            $this->simulation->retenir($simulation, (int) auth()->id());

        } catch (RuntimeException $e) {

            return back()->with('erreur', $e->getMessage());

        }



        return back()->with('succes', 'Scénario retenu comme référence.');

    }



    public function comparer(Request $request, Budget $budget): RedirectResponse

    {

        $this->authorize('simuler', $budget);



        $data = $request->validate([

            'simulations' => ['required', 'array', 'min:2'],

            'simulations.*' => ['integer', 'exists:budget_simulations,id'],

        ]);



        return redirect()->route('budget.enveloppe.simulations', [

            'budget' => $budget,

            'comparer' => $data['simulations'],

        ]);

    }

}

