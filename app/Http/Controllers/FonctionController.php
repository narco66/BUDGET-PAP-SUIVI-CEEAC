<?php

namespace App\Http\Controllers;

use App\Http\Requests\FonctionRequest;
use App\Models\Fonction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FonctionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Fonction::class);
        $fonctions = Fonction::withCount('utilisateurs')->orderBy('libelle')->paginate(20);

        return view('fonctions.index', compact('fonctions'));
    }

    public function create(): View
    {
        $this->authorize('create', Fonction::class);

        return view('fonctions.create');
    }

    public function store(FonctionRequest $request): RedirectResponse
    {
        $this->authorize('create', Fonction::class);
        Fonction::create($request->validated());

        return redirect()->route('fonctions.index')->with('succes', 'Fonction créée.');
    }

    public function edit(Fonction $fonction): View
    {
        $this->authorize('update', $fonction);

        return view('fonctions.edit', compact('fonction'));
    }

    public function update(FonctionRequest $request, Fonction $fonction): RedirectResponse
    {
        $this->authorize('update', $fonction);
        $fonction->update($request->validated());

        return redirect()->route('fonctions.index')->with('succes', 'Fonction mise à jour.');
    }

    public function destroy(Fonction $fonction): RedirectResponse
    {
        $this->authorize('delete', $fonction);

        if ($fonction->utilisateurs()->exists()) {
            return back()->with('erreur', 'Fonction utilisée par des utilisateurs.');
        }

        $fonction->delete();

        return redirect()->route('fonctions.index')->with('succes', 'Fonction supprimée.');
    }
}
