<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\ObjectifStrategiqueRequest;
use App\Models\Strategie\ObjectifStrategique;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObjectifStrategiqueController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ObjectifStrategique::class);

        $objectifs = $this->appliquerFiltresIndex(
            ObjectifStrategique::with(['planStrategique', 'orientationStrategique'])
                ->withCount('indicateurs')
                ->orderBy('ordre'),
            $request,
            ['code', 'intitule']
        )->paginate(20)->withQueryString();

        return view('strategie.objectifs.index', compact('objectifs'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ObjectifStrategique::class);

        $planId = $request->integer('plan');

        return view('strategie.objectifs.create', [
            'objectif' => new ObjectifStrategique(['plan_strategique_id' => $planId]),
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'orientations' => OrientationStrategique::when($planId, fn ($q) => $q->where('plan_strategique_id', $planId))
                ->orderBy('ordre')
                ->get(['id', 'code', 'intitule', 'plan_strategique_id']),
        ]);
    }

    public function store(ObjectifStrategiqueRequest $request): RedirectResponse
    {
        $this->authorize('create', ObjectifStrategique::class);

        $objectif = ObjectifStrategique::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('strategie.objectifs.show', $objectif)->with('succes', 'Objectif stratégique créé.');
    }

    public function show(ObjectifStrategique $objectifStrategique): View
    {
        $this->authorize('view', $objectifStrategique);
        $objectifStrategique->load(['planStrategique.vision', 'orientationStrategique', 'indicateurs']);

        return view('strategie.objectifs.show', ['objectif' => $objectifStrategique]);
    }

    public function edit(ObjectifStrategique $objectifStrategique): View
    {
        $this->authorize('update', $objectifStrategique);

        return view('strategie.objectifs.edit', [
            'objectif' => $objectifStrategique,
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'orientations' => OrientationStrategique::where('plan_strategique_id', $objectifStrategique->plan_strategique_id)
                ->orderBy('ordre')
                ->get(['id', 'code', 'intitule', 'plan_strategique_id']),
        ]);
    }

    public function update(ObjectifStrategiqueRequest $request, ObjectifStrategique $objectifStrategique): RedirectResponse
    {
        $this->authorize('update', $objectifStrategique);
        $objectifStrategique->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('strategie.objectifs.show', $objectifStrategique)->with('succes', 'Objectif mis à jour.');
    }

    public function destroy(ObjectifStrategique $objectifStrategique): RedirectResponse
    {
        $this->authorize('delete', $objectifStrategique);
        $objectifStrategique->delete();

        return redirect()->route('strategie.objectifs.index')->with('succes', 'Objectif supprimé.');
    }
}
