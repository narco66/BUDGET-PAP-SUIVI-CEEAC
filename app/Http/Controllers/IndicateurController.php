<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rbm\IndicateurRequest;
use App\Models\Indicateur;
use App\Models\SourceVerification;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Rbm\Niveaux;
use App\Services\Rbm\IndicateurIntegriteService;
use App\Services\Rbm\IndicateurPerformanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndicateurController extends Controller
{
    public function __construct(
        private readonly IndicateurIntegriteService $integrite,
        private readonly IndicateurPerformanceService $performance,
    ) {
    }

    public function index(Request $request, string $niveau, int $id): View
    {
        abort_unless($request->user()->can('indicateur.voir'), 403);
        $noeud = $this->noeud($niveau, $id);

        return view('indicateurs.index', [
            'niveau' => $niveau,
            'noeud' => $noeud,
            'config' => Niveaux::get($niveau),
            'indicateurs' => $noeud->indicateurs()->with([
                'mesures.auteur', 'mesures.historiques.utilisateur', 'typeIndicateur', 'uniteMesure',
            ])->get(),
            'performance' => $this->performance,
            'exerciceId' => $request->integer('exercice'),
            'typesIndicateurs' => TypeIndicateur::actif()->orderBy('ordre')->get(),
            'unitesMesure' => UniteMesure::actif()->orderBy('ordre')->get(),
            'sourcesVerification' => SourceVerification::actif()->orderBy('ordre')->get(),
        ]);
    }

    public function edit(Request $request, Indicateur $indicateur): View
    {
        abort_unless($request->user()->can('indicateur.modifier'), 403);
        $indicateur->load('sourcesVerification', 'rbm');

        return view('indicateurs.edit', [
            'indicateur' => $indicateur,
            'exerciceId' => $request->integer('exercice'),
            'typesIndicateurs' => TypeIndicateur::actif()->orderBy('ordre')->get(),
            'unitesMesure' => UniteMesure::actif()->orderBy('ordre')->get(),
            'sourcesVerification' => SourceVerification::actif()->orderBy('ordre')->get(),
        ]);
    }

    public function store(IndicateurRequest $request, string $niveau, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('indicateur.creer'), 403);
        $noeud = $this->noeud($niveau, $id);
        $data = $this->preparerDonnees($request->validated());

        $indicateur = $noeud->indicateurs()->create($data['attrs']);
        if ($data['sources']) {
            $indicateur->sourcesVerification()->sync($data['sources']);
        }

        return back()->with('succes', 'Indicateur ajouté.');
    }

    public function update(IndicateurRequest $request, Indicateur $indicateur): RedirectResponse
    {
        abort_unless($request->user()->can('indicateur.modifier'), 403);
        $data = $this->preparerDonnees($request->validated());

        $indicateur->update($data['attrs']);
        $indicateur->sourcesVerification()->sync($data['sources']);

        return back()->with('succes', 'Indicateur mis à jour.');
    }

    public function destroy(Request $request, Indicateur $indicateur): RedirectResponse
    {
        abort_unless($request->user()->can('indicateur.supprimer'), 403);
        $indicateur->delete();

        return back()->with('succes', 'Indicateur supprimé.');
    }

    /** @param  array<string,mixed>  $validated */
    private function preparerDonnees(array $validated): array
    {
        $sources = $validated['sources_verification'] ?? [];
        unset($validated['sources_verification']);

        return [
            'attrs' => $this->integrite->enrichirDepuisCatalogue($validated),
            'sources' => $sources,
        ];
    }

    private function noeud(string $niveau, int $id)
    {
        abort_unless(Niveaux::existe($niveau), 404);

        return Niveaux::get($niveau)['model']::findOrFail($id);
    }
}
