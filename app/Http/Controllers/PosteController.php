<?php

namespace App\Http\Controllers;

use App\Http\Requests\PosteRequest;
use App\Models\Fonction;
use App\Models\Poste;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosteController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Poste::class);
        $postes = Poste::with('fonction')->withCount('utilisateurs')->orderBy('libelle')->paginate(20);

        return view('postes.index', compact('postes'));
    }

    public function create(): View
    {
        $this->authorize('create', Poste::class);

        return view('postes.create', $this->options());
    }

    public function store(PosteRequest $request): RedirectResponse
    {
        $this->authorize('create', Poste::class);
        Poste::create($request->validated());

        return redirect()->route('postes.index')->with('succes', 'Poste créé.');
    }

    public function edit(Poste $poste): View
    {
        $this->authorize('update', $poste);

        return view('postes.edit', array_merge(compact('poste'), $this->options()));
    }

    public function update(PosteRequest $request, Poste $poste): RedirectResponse
    {
        $this->authorize('update', $poste);
        $poste->update($request->validated());

        return redirect()->route('postes.index')->with('succes', 'Poste mis à jour.');
    }

    public function destroy(Poste $poste): RedirectResponse
    {
        $this->authorize('delete', $poste);

        if ($poste->utilisateurs()->exists()) {
            return back()->with('erreur', 'Poste utilisé par des utilisateurs.');
        }

        $poste->delete();

        return redirect()->route('postes.index')->with('succes', 'Poste supprimé.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'fonctions' => Fonction::where('actif', true)->orderBy('libelle')->get(['id', 'libelle', 'code']),
        ];
    }
}
