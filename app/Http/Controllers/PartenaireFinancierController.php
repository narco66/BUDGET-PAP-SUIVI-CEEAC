<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartenaireFinancierRequest;
use App\Models\PartenaireFinancier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartenaireFinancierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PartenaireFinancier::class);

        $partenaires = PartenaireFinancier::query()
            ->withCount(['sources', 'conventions'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$request->q}%")
                ->orWhere('libelle', 'like', "%{$request->q}%")
                ->orWhere('sigle', 'like', "%{$request->q}%")))
            ->orderBy('libelle')
            ->paginate(20)
            ->withQueryString();

        return view('referentiel.ptf.index', compact('partenaires'));
    }

    public function create(): View
    {
        $this->authorize('create', PartenaireFinancier::class);

        return view('referentiel.ptf.create', ['partenaire' => new PartenaireFinancier(['actif' => true, 'type' => 'multilateral'])]);
    }

    public function store(PartenaireFinancierRequest $request): RedirectResponse
    {
        $this->authorize('create', PartenaireFinancier::class);

        $partenaire = PartenaireFinancier::create($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('partenaires.show', $partenaire)->with('succes', 'Partenaire financier créé.');
    }

    public function show(PartenaireFinancier $partenaire): View
    {
        $this->authorize('view', $partenaire);

        $partenaire->load(['sources', 'conventions.exercice', 'conventions.sourceFinancement']);

        return view('referentiel.ptf.show', compact('partenaire'));
    }

    public function edit(PartenaireFinancier $partenaire): View
    {
        $this->authorize('update', $partenaire);

        return view('referentiel.ptf.edit', compact('partenaire'));
    }

    public function update(PartenaireFinancierRequest $request, PartenaireFinancier $partenaire): RedirectResponse
    {
        $this->authorize('update', $partenaire);

        $partenaire->update($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('partenaires.show', $partenaire)->with('succes', 'Partenaire mis à jour.');
    }

    public function destroy(PartenaireFinancier $partenaire): RedirectResponse
    {
        $this->authorize('delete', $partenaire);

        if ($partenaire->conventions()->exists()) {
            return back()->with('erreur', 'Suppression impossible : des conventions sont rattachées.');
        }

        $partenaire->delete();

        return redirect()->route('partenaires.index')->with('succes', 'Partenaire supprimé.');
    }
}
