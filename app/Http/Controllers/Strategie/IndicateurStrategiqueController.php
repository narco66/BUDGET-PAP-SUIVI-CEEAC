<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\IndicateurStrategiqueRequest;
use App\Models\Indicateur;
use App\Models\Strategie\IndicateurStrategique;
use App\Models\Strategie\ObjectifStrategique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndicateurStrategiqueController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', IndicateurStrategique::class);

        $indicateurs = $this->appliquerFiltresIndex(
            IndicateurStrategique::with(['objectifStrategique.planStrategique', 'indicateurRbm'])
                ->orderBy('code'),
            $request,
            ['code', 'libelle']
        )->paginate(20)->withQueryString();

        return view('strategie.indicateurs.index', compact('indicateurs'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', IndicateurStrategique::class);

        return view('strategie.indicateurs.create', [
            'indicateur' => new IndicateurStrategique(['objectif_strategique_id' => $request->integer('objectif')]),
            'objectifs' => ObjectifStrategique::with('planStrategique')->orderBy('code')->get(),
            'indicateursRbm' => Indicateur::orderBy('code')->limit(200)->get(['id', 'code', 'libelle']),
        ]);
    }

    public function store(IndicateurStrategiqueRequest $request): RedirectResponse
    {
        $this->authorize('create', IndicateurStrategique::class);

        $indicateur = IndicateurStrategique::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('strategie.indicateurs.show', $indicateur)->with('succes', 'Indicateur stratégique créé.');
    }

    public function show(IndicateurStrategique $indicateurStrategique): View
    {
        $this->authorize('view', $indicateurStrategique);
        $indicateurStrategique->load(['objectifStrategique.planStrategique', 'indicateurRbm']);

        return view('strategie.indicateurs.show', ['indicateur' => $indicateurStrategique]);
    }

    public function edit(IndicateurStrategique $indicateurStrategique): View
    {
        $this->authorize('update', $indicateurStrategique);

        return view('strategie.indicateurs.edit', [
            'indicateur' => $indicateurStrategique,
            'objectifs' => ObjectifStrategique::with('planStrategique')->orderBy('code')->get(),
            'indicateursRbm' => Indicateur::orderBy('code')->limit(200)->get(['id', 'code', 'libelle']),
        ]);
    }

    public function update(IndicateurStrategiqueRequest $request, IndicateurStrategique $indicateurStrategique): RedirectResponse
    {
        $this->authorize('update', $indicateurStrategique);
        $indicateurStrategique->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('strategie.indicateurs.show', $indicateurStrategique)->with('succes', 'Indicateur mis à jour.');
    }

    public function destroy(IndicateurStrategique $indicateurStrategique): RedirectResponse
    {
        $this->authorize('delete', $indicateurStrategique);
        $indicateurStrategique->delete();

        return redirect()->route('strategie.indicateurs.index')->with('succes', 'Indicateur supprimé.');
    }
}
