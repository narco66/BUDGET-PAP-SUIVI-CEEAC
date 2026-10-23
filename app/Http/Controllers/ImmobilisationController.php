<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImmobilisationRequest;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Immobilisation;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImmobilisationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('immobilisation.voir'), 403);

        $items = Immobilisation::with('structure')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('categorie'), fn ($q) => $q->where('categorie', $request->categorie))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('immobilisations.index', [
            'immobilisations' => $items,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'categories' => Immobilisation::CATEGORIES,
            'statuts' => Immobilisation::STATUTS,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('immobilisation.creer'), 403);

        return view('immobilisations.form', $this->options() + [
            'immobilisation' => new Immobilisation([
                'statut' => 'en_service',
                'categorie' => 'mobilier',
                'duree_amortissement_ans' => 5,
                'date_acquisition' => now()->toDateString(),
            ]),
        ]);
    }

    public function store(ImmobilisationRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('immobilisation.creer'), 403);
        $data = $request->validated();
        $data['numero'] ??= $this->numero($data['exercice_id']);
        Immobilisation::create($data);

        return redirect()->route('immobilisations.index')->with('succes', 'Immobilisation enregistrée.');
    }

    public function show(Request $request, Immobilisation $immobilisation): View
    {
        abort_unless($request->user()->can('immobilisation.voir'), 403);
        $immobilisation->load(['structure', 'engagement', 'exercice']);

        return view('immobilisations.show', compact('immobilisation'));
    }

    public function edit(Request $request, Immobilisation $immobilisation): View
    {
        abort_unless($request->user()->can('immobilisation.modifier'), 403);

        return view('immobilisations.form', $this->options() + compact('immobilisation'));
    }

    public function update(ImmobilisationRequest $request, Immobilisation $immobilisation): RedirectResponse
    {
        abort_unless($request->user()->can('immobilisation.modifier'), 403);
        $immobilisation->update($request->validated());

        return redirect()->route('immobilisations.index')->with('succes', 'Immobilisation mise à jour.');
    }

    public function destroy(Request $request, Immobilisation $immobilisation): RedirectResponse
    {
        abort_unless($request->user()->can('immobilisation.supprimer'), 403);
        $immobilisation->delete();

        return redirect()->route('immobilisations.index')->with('succes', 'Immobilisation supprimée.');
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Immobilisation::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('IMM-%d-%03d', $annee, $seq);
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::actif()->orderBy('libelle')->get(),
            'engagements' => Engagement::latest('id')->limit(100)->get(),
            'categories' => Immobilisation::CATEGORIES,
            'statuts' => Immobilisation::STATUTS,
        ];
    }
}
