<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\OrientationStrategiqueRequest;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\Vision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrientationStrategiqueController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', OrientationStrategique::class);

        $orientations = $this->appliquerFiltresIndex(
            OrientationStrategique::with('planStrategique', 'vision')->orderBy('ordre'),
            $request,
            ['code', 'intitule']
        )->paginate(20)->withQueryString();

        return view('strategie.orientations.index', compact('orientations'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', OrientationStrategique::class);

        return view('strategie.orientations.create', [
            'orientation' => new OrientationStrategique(['plan_strategique_id' => $request->integer('plan')]),
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'visions' => Vision::orderBy('reference')->get(['id', 'reference', 'intitule']),
        ]);
    }

    public function store(OrientationStrategiqueRequest $request): RedirectResponse
    {
        $this->authorize('create', OrientationStrategique::class);

        $orientation = OrientationStrategique::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('strategie.orientations.show', $orientation)->with('succes', 'Orientation créée.');
    }

    public function show(OrientationStrategique $orientation): View
    {
        $this->authorize('view', $orientation);
        $orientation->load(['planStrategique.vision', 'programmesStrategiques']);

        return view('strategie.orientations.show', compact('orientation'));
    }

    public function edit(OrientationStrategique $orientation): View
    {
        $this->authorize('update', $orientation);

        return view('strategie.orientations.edit', [
            'orientation' => $orientation,
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'visions' => Vision::orderBy('reference')->get(['id', 'reference', 'intitule']),
        ]);
    }

    public function update(OrientationStrategiqueRequest $request, OrientationStrategique $orientation): RedirectResponse
    {
        $this->authorize('update', $orientation);
        $orientation->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('strategie.orientations.show', $orientation)->with('succes', 'Orientation mise à jour.');
    }

    public function destroy(OrientationStrategique $orientation): RedirectResponse
    {
        $this->authorize('delete', $orientation);
        $orientation->delete();

        return redirect()->route('strategie.orientations.index')->with('succes', 'Orientation supprimée.');
    }
}
