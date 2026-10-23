<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\PortefeuilleProjetRequest;
use App\Models\Exercice;
use App\Models\Strategie\PortefeuilleProjet;
use App\Models\Strategie\ProjetStrategique;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortefeuilleProjetController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PortefeuilleProjet::class);

        $portefeuilles = $this->appliquerFiltresIndex(
            PortefeuilleProjet::with('exercice', 'responsable')->withCount('projets')->orderBy('reference'),
            $request
        )->paginate(20)->withQueryString();

        return view('strategie.portefeuilles.index', compact('portefeuilles'));
    }

    public function create(): View
    {
        $this->authorize('create', PortefeuilleProjet::class);

        return view('strategie.portefeuilles.create', $this->options() + [
            'portefeuille' => new PortefeuilleProjet,
        ]);
    }

    public function store(PortefeuilleProjetRequest $request): RedirectResponse
    {
        $this->authorize('create', PortefeuilleProjet::class);

        $data = $request->validated();
        $projetIds = $data['projet_ids'] ?? [];
        unset($data['projet_ids']);

        $portefeuille = PortefeuilleProjet::create($data + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        if ($projetIds !== []) {
            $portefeuille->projets()->sync($projetIds);
        }

        return redirect()->route('strategie.portefeuilles.show', $portefeuille)->with('succes', 'Portefeuille créé.');
    }

    public function show(PortefeuilleProjet $portefeuille): View
    {
        $this->authorize('view', $portefeuille);
        $portefeuille->load(['exercice', 'responsable', 'projets.programmeStrategique']);

        return view('strategie.portefeuilles.show', compact('portefeuille'));
    }

    public function edit(PortefeuilleProjet $portefeuille): View
    {
        $this->authorize('update', $portefeuille);
        $portefeuille->load('projets');

        return view('strategie.portefeuilles.edit', $this->options() + compact('portefeuille'));
    }

    public function update(PortefeuilleProjetRequest $request, PortefeuilleProjet $portefeuille): RedirectResponse
    {
        $this->authorize('update', $portefeuille);

        $data = $request->validated();
        $projetIds = $data['projet_ids'] ?? [];
        unset($data['projet_ids']);

        $portefeuille->update($data + ['updated_by' => auth()->id()]);
        $portefeuille->projets()->sync($projetIds);

        return redirect()->route('strategie.portefeuilles.show', $portefeuille)->with('succes', 'Portefeuille mis à jour.');
    }

    public function destroy(PortefeuilleProjet $portefeuille): RedirectResponse
    {
        $this->authorize('delete', $portefeuille);
        $portefeuille->delete();

        return redirect()->route('strategie.portefeuilles.index')->with('succes', 'Portefeuille supprimé.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee']),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name', 'matricule']),
            'projetsDisponibles' => ProjetStrategique::orderBy('code')->get(['id', 'code', 'intitule']),
        ];
    }
}
