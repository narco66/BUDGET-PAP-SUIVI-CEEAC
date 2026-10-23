<?php

namespace App\Http\Controllers;

use App\Http\Requests\SourceFinancementRequest;
use App\Models\PartenaireFinancier;
use App\Models\SourceFinancement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SourceFinancementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SourceFinancement::class);

        $sources = SourceFinancement::query()
            ->with('partenaire')
            ->withCount('lignesBudgetaires')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$request->q}%")
                ->orWhere('libelle', 'like', "%{$request->q}%")))
            ->orderBy('type')->orderBy('libelle')
            ->paginate(20)
            ->withQueryString();

        return view('referentiel.sources.index', compact('sources'));
    }

    public function create(): View
    {
        $this->authorize('create', SourceFinancement::class);

        return view('referentiel.sources.create', [
            'source' => new SourceFinancement(['actif' => true, 'type' => 'interne_ceeac']),
            'partenaires' => PartenaireFinancier::where('actif', true)->orderBy('libelle')->get(),
        ]);
    }

    public function store(SourceFinancementRequest $request): RedirectResponse
    {
        $this->authorize('create', SourceFinancement::class);

        $source = SourceFinancement::create($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('sources.show', $source)->with('succes', 'Source de financement créée.');
    }

    public function show(SourceFinancement $source): View
    {
        $this->authorize('view', $source);

        $source->load('partenaire', 'conventions.partenaire');
        $source->loadCount('lignesBudgetaires');

        return view('referentiel.sources.show', compact('source'));
    }

    public function edit(SourceFinancement $source): View
    {
        $this->authorize('update', $source);

        return view('referentiel.sources.edit', [
            'source' => $source,
            'partenaires' => PartenaireFinancier::where('actif', true)->orderBy('libelle')->get(),
        ]);
    }

    public function update(SourceFinancementRequest $request, SourceFinancement $source): RedirectResponse
    {
        $this->authorize('update', $source);

        $source->update($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('sources.show', $source)->with('succes', 'Source mise à jour.');
    }

    public function destroy(SourceFinancement $source): RedirectResponse
    {
        $this->authorize('delete', $source);

        if ($source->lignesBudgetaires()->exists()) {
            return back()->with('erreur', 'Suppression impossible : des lignes budgétaires utilisent cette source.');
        }

        $source->delete();

        return redirect()->route('sources.index')->with('succes', 'Source supprimée.');
    }
}
