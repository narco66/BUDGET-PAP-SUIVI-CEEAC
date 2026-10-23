<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\PlanStrategiqueRequest;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\Vision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanStrategiqueController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PlanStrategique::class);

        $plans = $this->appliquerFiltresIndex(
            PlanStrategique::with('vision')->orderByDesc('date_debut'),
            $request
        )->paginate(20)->withQueryString();

        return view('strategie.plans.index', compact('plans'));
    }

    public function create(): View
    {
        $this->authorize('create', PlanStrategique::class);

        return view('strategie.plans.create', [
            'plan' => new PlanStrategique,
            'visions' => Vision::orderBy('reference')->get(['id', 'reference', 'intitule']),
        ]);
    }

    public function store(PlanStrategiqueRequest $request): RedirectResponse
    {
        $this->authorize('create', PlanStrategique::class);

        $plan = PlanStrategique::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('strategie.plans.show', $plan)->with('succes', 'Plan stratégique créé.');
    }

    public function show(PlanStrategique $plan): View
    {
        $this->authorize('view', $plan);
        $plan->load(['vision', 'orientations', 'objectifs.indicateurs', 'programmationsPluriannuelles', 'risques', 'pieces.auteur', 'validateur']);

        return view('strategie.plans.show', compact('plan'));
    }

    public function edit(PlanStrategique $plan): View
    {
        $this->authorize('update', $plan);

        return view('strategie.plans.edit', [
            'plan' => $plan,
            'visions' => Vision::orderBy('reference')->get(['id', 'reference', 'intitule']),
        ]);
    }

    public function update(PlanStrategiqueRequest $request, PlanStrategique $plan): RedirectResponse
    {
        $this->authorize('update', $plan);
        $plan->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('strategie.plans.show', $plan)->with('succes', 'Plan stratégique mis à jour.');
    }

    public function destroy(PlanStrategique $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);
        $plan->delete();

        return redirect()->route('strategie.plans.index')->with('succes', 'Plan stratégique supprimé.');
    }
}
