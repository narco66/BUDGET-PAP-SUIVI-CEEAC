<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\RisqueStrategiqueRequest;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\Strategie\RisqueStrategique;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RisqueStrategiqueController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', RisqueStrategique::class);

        $risques = $this->appliquerFiltresIndex(
            RisqueStrategique::with(['planStrategique', 'projet', 'responsable'])
                ->orderBy('code'),
            $request,
            ['code', 'description']
        )->paginate(20)->withQueryString();

        return view('strategie.risques.index', compact('risques'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', RisqueStrategique::class);

        $planId = $request->integer('plan');

        return view('strategie.risques.create', [
            'risque' => new RisqueStrategique(['plan_strategique_id' => $planId]),
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'projets' => ProjetStrategique::when($planId, fn ($q) => $q->whereHas(
                'programmeStrategique.orientationStrategique',
                fn ($o) => $o->where('plan_strategique_id', $planId)
            ))->orderBy('code')->get(['id', 'code', 'intitule']),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(RisqueStrategiqueRequest $request): RedirectResponse
    {
        $this->authorize('create', RisqueStrategique::class);

        $risque = RisqueStrategique::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('strategie.risques.show', $risque)->with('succes', 'Risque stratégique enregistré.');
    }

    public function show(RisqueStrategique $risqueStrategique): View
    {
        $this->authorize('view', $risqueStrategique);
        $risqueStrategique->load(['planStrategique.vision', 'projet', 'responsable']);

        return view('strategie.risques.show', ['risque' => $risqueStrategique]);
    }

    public function edit(RisqueStrategique $risqueStrategique): View
    {
        $this->authorize('update', $risqueStrategique);

        return view('strategie.risques.edit', [
            'risque' => $risqueStrategique,
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'projets' => ProjetStrategique::orderBy('code')->get(['id', 'code', 'intitule']),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(RisqueStrategiqueRequest $request, RisqueStrategique $risqueStrategique): RedirectResponse
    {
        $this->authorize('update', $risqueStrategique);
        $risqueStrategique->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('strategie.risques.show', $risqueStrategique)->with('succes', 'Risque mis à jour.');
    }

    public function destroy(RisqueStrategique $risqueStrategique): RedirectResponse
    {
        $this->authorize('delete', $risqueStrategique);
        $risqueStrategique->delete();

        return redirect()->route('strategie.risques.index')->with('succes', 'Risque supprimé.');
    }
}
