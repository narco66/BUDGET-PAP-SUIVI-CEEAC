<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormationRequest;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Formation;
use App\Models\SourceFinancement;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('formation.voir'), 403);

        $formations = Formation::with(['structure', 'sourceFinancement'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')->paginate(20)->withQueryString();

        return view('formations.index', [
            'formations' => $formations,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'types' => Formation::TYPES,
            'statuts' => Formation::STATUTS,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('formation.creer'), 403);

        return view('formations.form', $this->options() + ['formation' => new Formation(['statut' => 'planifie', 'type' => 'formation'])]);
    }

    public function store(FormationRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('formation.creer'), 403);
        $data = $request->validated();
        $data['numero'] ??= $this->numero($data['exercice_id']);
        Formation::create($data);

        return redirect()->route('formations.index')->with('succes', 'Formation enregistrée.');
    }

    public function show(Request $request, Formation $formation): View
    {
        abort_unless($request->user()->can('formation.voir'), 403);
        $formation->load(['structure', 'sourceFinancement', 'engagement']);

        return view('formations.show', compact('formation'));
    }

    public function edit(Request $request, Formation $formation): View
    {
        abort_unless($request->user()->can('formation.modifier'), 403);

        return view('formations.form', $this->options() + compact('formation'));
    }

    public function update(FormationRequest $request, Formation $formation): RedirectResponse
    {
        abort_unless($request->user()->can('formation.modifier'), 403);
        $formation->update($request->validated());

        return redirect()->route('formations.index')->with('succes', 'Formation mise à jour.');
    }

    public function destroy(Request $request, Formation $formation): RedirectResponse
    {
        abort_unless($request->user()->can('formation.supprimer'), 403);
        $formation->delete();

        return redirect()->route('formations.index')->with('succes', 'Formation supprimée.');
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Formation::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('FOR-%d-%03d', $annee, $seq);
    }

    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::actif()->orderBy('libelle')->get(),
            'sources' => SourceFinancement::orderBy('libelle')->get(),
            'engagements' => Engagement::latest('id')->limit(100)->get(),
            'types' => Formation::TYPES,
            'statuts' => Formation::STATUTS,
        ];
    }
}
