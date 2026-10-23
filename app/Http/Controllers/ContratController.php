<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContratRequest;
use App\Models\Contrat;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Marche;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContratController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('contrat.voir'), 403);

        $contrats = Contrat::with(['marche', 'engagement', 'structure'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')->paginate(20)->withQueryString();

        return view('contrats.index', [
            'contrats' => $contrats,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'statuts' => Contrat::STATUTS,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('contrat.creer'), 403);

        return view('contrats.form', $this->options() + ['contrat' => new Contrat(['statut' => 'projet'])]);
    }

    public function store(ContratRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('contrat.creer'), 403);
        $data = $request->validated();
        $data['numero'] ??= $this->numero($data['exercice_id']);
        Contrat::create($data);

        return redirect()->route('contrats.index')->with('succes', 'Contrat enregistré.');
    }

    public function show(Request $request, Contrat $contrat): View
    {
        abort_unless($request->user()->can('contrat.voir'), 403);
        $contrat->load(['marche', 'engagement.expressionBesoin', 'structure']);

        return view('contrats.show', compact('contrat'));
    }

    public function edit(Request $request, Contrat $contrat): View
    {
        abort_unless($request->user()->can('contrat.modifier'), 403);

        return view('contrats.form', $this->options() + compact('contrat'));
    }

    public function update(ContratRequest $request, Contrat $contrat): RedirectResponse
    {
        abort_unless($request->user()->can('contrat.modifier'), 403);
        $contrat->update($request->validated());

        return redirect()->route('contrats.index')->with('succes', 'Contrat mis à jour.');
    }

    public function destroy(Request $request, Contrat $contrat): RedirectResponse
    {
        abort_unless($request->user()->can('contrat.supprimer'), 403);
        $contrat->delete();

        return redirect()->route('contrats.index')->with('succes', 'Contrat supprimé.');
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Contrat::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('CTR-%d-%03d', $annee, $seq);
    }

    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::actif()->orderBy('libelle')->get(),
            'marches' => Marche::latest('id')->limit(100)->get(),
            'engagements' => Engagement::latest('id')->limit(100)->get(),
            'types' => Contrat::TYPES,
            'statuts' => Contrat::STATUTS,
        ];
    }
}
