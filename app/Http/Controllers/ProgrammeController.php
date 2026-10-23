<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProgrammeRequest;
use App\Models\Pilier;
use App\Models\Programme;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProgrammeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Programme::class);
        $programmes = Programme::with('structure', 'pilier')->orderBy('code')->paginate(20);

        return view('programmes.index', compact('programmes'));
    }

    public function create(): View
    {
        $this->authorize('create', Programme::class);

        return view('programmes.create', $this->options());
    }

    public function store(ProgrammeRequest $request): RedirectResponse
    {
        $this->authorize('create', Programme::class);
        Programme::create($request->validated());

        return redirect()->route('programmes.index')->with('succes', 'Programme créé.');
    }

    public function edit(Programme $programme): View
    {
        $this->authorize('update', $programme);

        return view('programmes.edit', array_merge(compact('programme'), $this->options()));
    }

    public function update(ProgrammeRequest $request, Programme $programme): RedirectResponse
    {
        $this->authorize('update', $programme);
        $programme->update($request->validated());

        return redirect()->route('programmes.index')->with('succes', 'Programme mis à jour.');
    }

    public function destroy(Programme $programme): RedirectResponse
    {
        $this->authorize('delete', $programme);
        $programme->delete();

        return redirect()->route('programmes.index')->with('succes', 'Programme supprimé.');
    }

    private function options(): array
    {
        return [
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'piliers' => Pilier::orderBy('code')->get(['id', 'code', 'intitule']),
        ];
    }
}
