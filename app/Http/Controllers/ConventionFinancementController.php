<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConventionFinancementRequest;
use App\Models\ConventionFinancement;
use App\Models\Exercice;
use App\Models\PartenaireFinancier;
use App\Models\SourceFinancement;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ConventionFinancementController extends Controller
{
    public function create(PartenaireFinancier $partenaire): View
    {
        $this->authorize('create', ConventionFinancement::class);

        return view('referentiel.conventions.create', [
            'convention' => new ConventionFinancement(['partenaire_id' => $partenaire->id, 'statut' => 'brouillon', 'actif' => true]),
            'partenaire' => $partenaire,
            'partenaires' => PartenaireFinancier::orderBy('libelle')->get(),
            'sources' => SourceFinancement::where('actif', true)->orderBy('libelle')->get(),
            'exercices' => Exercice::orderByDesc('annee')->get(),
        ]);
    }

    public function store(ConventionFinancementRequest $request): RedirectResponse
    {
        $this->authorize('create', ConventionFinancement::class);

        $convention = ConventionFinancement::create($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('partenaires.show', $convention->partenaire_id)->with('succes', 'Convention enregistrée.');
    }

    public function edit(ConventionFinancement $convention): View
    {
        $this->authorize('update', $convention);

        return view('referentiel.conventions.edit', [
            'convention' => $convention,
            'partenaire' => $convention->partenaire,
            'partenaires' => PartenaireFinancier::orderBy('libelle')->get(),
            'sources' => SourceFinancement::where('actif', true)->orderBy('libelle')->get(),
            'exercices' => Exercice::orderByDesc('annee')->get(),
        ]);
    }

    public function update(ConventionFinancementRequest $request, ConventionFinancement $convention): RedirectResponse
    {
        $this->authorize('update', $convention);

        $convention->update($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return redirect()->route('partenaires.show', $convention->partenaire_id)->with('succes', 'Convention mise à jour.');
    }

    public function destroy(ConventionFinancement $convention): RedirectResponse
    {
        $this->authorize('delete', $convention);

        $partenaireId = $convention->partenaire_id;
        $convention->delete();

        return redirect()->route('partenaires.show', $partenaireId)->with('succes', 'Convention supprimée.');
    }
}
