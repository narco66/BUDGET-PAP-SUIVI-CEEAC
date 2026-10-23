<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\ProjetStrategiqueRequest;
use App\Models\Pilier;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjetStrategiqueController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProjetStrategique::class);

        $projets = $this->appliquerFiltresIndex(
            ProjetStrategique::with('programmeStrategique', 'responsable')->orderBy('code'),
            $request,
            ['code', 'intitule']
        )->paginate(20)->withQueryString();

        return view('strategie.projets.index', compact('projets'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProjetStrategique::class);

        return view('strategie.projets.create', $this->options() + [
            'projet' => new ProjetStrategique([
                'programme_strategique_id' => $request->integer('programme'),
                'responsable_user_id' => auth()->id(),
            ]),
        ]);
    }

    public function store(ProjetStrategiqueRequest $request): RedirectResponse
    {
        $this->authorize('create', ProjetStrategique::class);

        $projet = ProjetStrategique::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('strategie.projets.show', $projet)->with('succes', 'Projet créé.');
    }

    public function show(ProjetStrategique $projet): View
    {
        $this->authorize('view', $projet);
        $projet->load(['programmeStrategique.orientationStrategique', 'responsable', 'pilier', 'portefeuilles', 'risques']);

        return view('strategie.projets.show', compact('projet'));
    }

    public function edit(ProjetStrategique $projet): View
    {
        $this->authorize('update', $projet);

        return view('strategie.projets.edit', $this->options() + compact('projet'));
    }

    public function update(ProjetStrategiqueRequest $request, ProjetStrategique $projet): RedirectResponse
    {
        $this->authorize('update', $projet);
        $projet->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('strategie.projets.show', $projet)->with('succes', 'Projet mis à jour.');
    }

    public function destroy(ProjetStrategique $projet): RedirectResponse
    {
        $this->authorize('delete', $projet);
        $projet->delete();

        return redirect()->route('strategie.projets.index')->with('succes', 'Projet supprimé.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'programmes' => ProgrammeStrategique::orderBy('code')->get(['id', 'code', 'intitule']),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name', 'matricule']),
            'piliers' => Pilier::orderBy('code')->get(['id', 'code', 'intitule']),
        ];
    }
}
