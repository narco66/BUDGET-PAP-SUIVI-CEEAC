<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\ProgrammationPluriannuelleRequest;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProgrammationPluriannuelle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgrammationPluriannuelleController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProgrammationPluriannuelle::class);

        $programmations = $this->appliquerFiltresIndex(
            ProgrammationPluriannuelle::with('planStrategique')->withCount('programmationsAnnuelles')->orderByDesc('annee_debut'),
            $request
        )->paginate(20)->withQueryString();

        return view('strategie.programmations-pluri.index', compact('programmations'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProgrammationPluriannuelle::class);

        return view('strategie.programmations-pluri.create', [
            'programmation' => new ProgrammationPluriannuelle(['plan_strategique_id' => $request->integer('plan')]),
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
        ]);
    }

    public function store(ProgrammationPluriannuelleRequest $request): RedirectResponse
    {
        $this->authorize('create', ProgrammationPluriannuelle::class);

        $programmation = ProgrammationPluriannuelle::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('strategie.programmations-pluri.show', $programmation)->with('succes', 'Programmation pluriannuelle créée.');
    }

    public function show(ProgrammationPluriannuelle $programmationPluriannuelle): View
    {
        $this->authorize('view', $programmationPluriannuelle);
        $programmationPluriannuelle->load(['planStrategique.vision', 'programmationsAnnuelles.exercice']);

        return view('strategie.programmations-pluri.show', ['programmation' => $programmationPluriannuelle]);
    }

    public function edit(ProgrammationPluriannuelle $programmationPluriannuelle): View
    {
        $this->authorize('update', $programmationPluriannuelle);

        return view('strategie.programmations-pluri.edit', [
            'programmation' => $programmationPluriannuelle,
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
        ]);
    }

    public function update(ProgrammationPluriannuelleRequest $request, ProgrammationPluriannuelle $programmationPluriannuelle): RedirectResponse
    {
        $this->authorize('update', $programmationPluriannuelle);
        $programmationPluriannuelle->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('strategie.programmations-pluri.show', $programmationPluriannuelle)->with('succes', 'Programmation mise à jour.');
    }

    public function destroy(ProgrammationPluriannuelle $programmationPluriannuelle): RedirectResponse
    {
        $this->authorize('delete', $programmationPluriannuelle);
        $programmationPluriannuelle->delete();

        return redirect()->route('strategie.programmations-pluri.index')->with('succes', 'Programmation supprimée.');
    }
}
