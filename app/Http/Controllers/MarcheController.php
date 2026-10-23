<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarcheRequest;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Marche;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarcheController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('marche.voir'), 403);

        $marches = Marche::with(['engagement', 'expressionBesoin', 'structure', 'contrat'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')->paginate(20)->withQueryString();

        return view('marches.index', [
            'marches' => $marches,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'statuts' => Marche::STATUTS,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('marche.creer'), 403);

        return view('marches.form', $this->options() + ['marche' => new Marche(['statut' => 'planifie'])]);
    }

    public function store(MarcheRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('marche.creer'), 403);
        $data = $request->validated();
        $data['numero'] ??= $this->numero($data['exercice_id']);
        Marche::create($data);

        return redirect()->route('marches.index')->with('succes', 'Marché enregistré.');
    }

    public function show(Request $request, Marche $marche): View
    {
        abort_unless($request->user()->can('marche.voir'), 403);
        $marche->load(['engagement.expressionBesoin', 'expressionBesoin', 'structure', 'contrat']);

        return view('marches.show', compact('marche'));
    }

    public function edit(Request $request, Marche $marche): View
    {
        abort_unless($request->user()->can('marche.modifier'), 403);

        return view('marches.form', $this->options() + compact('marche'));
    }

    public function update(MarcheRequest $request, Marche $marche): RedirectResponse
    {
        abort_unless($request->user()->can('marche.modifier'), 403);
        $marche->update($request->validated());

        return redirect()->route('marches.index')->with('succes', 'Marché mis à jour.');
    }

    public function destroy(Request $request, Marche $marche): RedirectResponse
    {
        abort_unless($request->user()->can('marche.supprimer'), 403);
        $marche->delete();

        return redirect()->route('marches.index')->with('succes', 'Marché supprimé.');
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Marche::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('MAR-%d-%03d', $annee, $seq);
    }

    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::actif()->orderBy('libelle')->get(),
            'engagements' => Engagement::where('type', 'marche')->latest('id')->limit(100)->get(),
            'besoins' => ExpressionBesoin::latest('id')->limit(100)->get(),
            'types' => Marche::TYPES,
            'modes' => Marche::MODES,
            'statuts' => Marche::STATUTS,
        ];
    }
}
