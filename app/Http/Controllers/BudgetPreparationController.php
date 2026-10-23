<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetPreparation;
use App\Services\Budget\BudgetArbitrageService;
use App\Services\Budget\BudgetConsolidationService;
use App\Services\Budget\BudgetPreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Préparation, validation hiérarchique et arbitrage DPPB (phase B2). */
class BudgetPreparationController extends Controller
{
    public function __construct(
        private readonly BudgetPreparationService $preparation,
        private readonly BudgetArbitrageService $arbitrage,
        private readonly BudgetConsolidationService $consolidation,
    ) {
    }

    public function index(Budget $budget, Request $request): View
    {
        $this->authorize('preparer', $budget);

        $structureId = $request->integer('structure') ?: null;
        $preparations = $this->preparation->preparations($budget, $structureId);
        $synthese = $this->preparation->synthese($budget);
        $consolidation = $this->consolidation->syntheseGlobale($budget);
        $parStructure = $this->consolidation->parStructure($budget);
        $historiqueArbitrages = $this->arbitrage->historique($budget)->take(20);

        return view('budget.enveloppe.preparations', compact(
            'budget', 'preparations', 'synthese', 'consolidation', 'parStructure', 'historiqueArbitrages', 'structureId'
        ));
    }

    public function initialiser(Budget $budget): RedirectResponse
    {
        $this->authorize('preparer', $budget);

        try {
            $count = $this->preparation->initialiser($budget);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "Préparations initialisées ({$count} nouvelle(s) ligne(s)).");
    }

    public function update(Request $request, Budget $budget, BudgetPreparation $preparation): RedirectResponse
    {
        $this->authorize('preparer', $budget);
        abort_unless((int) $preparation->budget_id === (int) $budget->id, 404);

        $data = $request->validate([
            'montant_demande' => ['nullable', 'numeric', 'min:0'],
            'montant_propose' => ['nullable', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->preparation->mettreAJour($preparation, $data, (int) auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Proposition mise à jour.');
    }

    public function soumettre(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('preparer', $budget);

        $data = $request->validate([
            'structure_id' => ['required', 'exists:structures,id'],
        ]);

        try {
            $count = $this->preparation->soumettreStructure($budget, (int) $data['structure_id'], (int) auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "{$count} proposition(s) soumise(s) par le service.");
    }

    public function valider(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('validerPreparation', [$budget, $request->input('niveau')]);

        $data = $request->validate([
            'niveau' => ['required', 'in:direction,departement,dppb'],
            'structure_id' => ['nullable', 'exists:structures,id'],
        ]);

        try {
            $count = $this->preparation->validerNiveau(
                $budget,
                $data['niveau'],
                (int) auth()->id(),
                isset($data['structure_id']) ? (int) $data['structure_id'] : null,
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "{$count} proposition(s) validée(s) ({$data['niveau']}).");
    }

    public function arbitrer(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('arbitrer', $budget);

        if ($request->has('decisions')) {
            $data = $request->validate([
                'decisions' => ['required', 'array'],
                'decisions.*.preparation_id' => ['required', 'integer'],
                'decisions.*.montant_apres' => ['required', 'numeric', 'min:0'],
                'decisions.*.motif' => ['nullable', 'string', 'max:1000'],
            ]);

            $stats = $this->arbitrage->arbitrerLot($budget, $data['decisions'], (int) auth()->id());

            return back()->with('succes', "Arbitrage : {$stats['arbitrages']} ajustement(s), {$stats['ignorees']} ignoré(s).");
        }

        $data = $request->validate([
            'preparation_id' => ['required', 'exists:budget_preparations,id'],
            'montant_apres' => ['required', 'numeric', 'min:0'],
            'motif' => ['required', 'string', 'max:1000'],
        ]);

        $prep = BudgetPreparation::where('budget_id', $budget->id)->findOrFail($data['preparation_id']);

        try {
            $this->arbitrage->arbitrer($prep, (float) $data['montant_apres'], $data['motif'], (int) auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Arbitrage enregistré.');
    }

    public function consolider(Budget $budget): RedirectResponse
    {
        $this->authorize('consolider', $budget);

        try {
            $stats = $this->consolidation->consolider($budget, (int) auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "Consolidation : {$stats['lignes']} ligne(s), ".number_format($stats['montant_total'], 0, ',', ' ').' FCFA.');
    }
}
