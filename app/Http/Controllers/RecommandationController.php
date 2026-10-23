<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Models\Recommandation;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecommandationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('recommandation.voir'), 403);

        $recommandations = Recommandation::with(['structure', 'responsable'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')->paginate(20)->withQueryString();

        return view('recommandations.index', [
            'recommandations' => $recommandations,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'sources' => Recommandation::SOURCES,
            'statuts' => Recommandation::STATUTS,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('recommandation.creer'), 403);

        return view('recommandations.form', $this->options() + ['recommandation' => new Recommandation(['statut' => 'en_attente', 'priorite' => 'moyenne'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('recommandation.creer'), 403);
        $data = $this->valider($request);
        $data['numero'] ??= $this->numero($data['exercice_id']);
        $recommandation = Recommandation::create($data);
        app(\App\Services\Notifications\SuiviEvalNotificationService::class)
            ->notifierRecommandationCreee($recommandation);

        return redirect()->route('recommandations.index')->with('succes', 'Recommandation enregistrée.');
    }

    public function show(Request $request, Recommandation $recommandation): View
    {
        abort_unless($request->user()->can('recommandation.voir'), 403);
        $recommandation->load(['structure', 'responsable', 'exercice']);

        return view('recommandations.show', compact('recommandation'));
    }

    public function edit(Request $request, Recommandation $recommandation): View
    {
        abort_unless($request->user()->can('recommandation.modifier'), 403);

        return view('recommandations.form', $this->options() + compact('recommandation'));
    }

    public function update(Request $request, Recommandation $recommandation): RedirectResponse
    {
        abort_unless($request->user()->can('recommandation.modifier'), 403);
        $recommandation->update($this->valider($request, $recommandation));

        return redirect()->route('recommandations.index')->with('succes', 'Recommandation mise à jour.');
    }

    public function destroy(Request $request, Recommandation $recommandation): RedirectResponse
    {
        abort_unless($request->user()->can('recommandation.supprimer'), 403);
        $recommandation->delete();

        return redirect()->route('recommandations.index')->with('succes', 'Recommandation supprimée.');
    }

    private function valider(Request $request, ?Recommandation $rec = null): array
    {
        return $request->validate([
            'numero' => 'nullable|string|max:40|unique:recommandations,numero'.($rec ? ','.$rec->id : ''),
            'exercice_id' => 'required|exists:exercices,id',
            'structure_id' => 'nullable|exists:structures,id',
            'responsable_user_id' => 'nullable|exists:users,id',
            'source' => 'required|in:'.implode(',', array_keys(Recommandation::SOURCES)),
            'categorie' => 'nullable|in:'.implode(',', array_keys(Recommandation::CATEGORIES)),
            'evaluation_periodique_id' => 'nullable|exists:evaluations_periodiques,id',
            'reference_reunion' => 'nullable|string|max:255',
            'date_emission' => 'nullable|date',
            'libelle' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priorite' => 'required|in:'.implode(',', array_keys(Recommandation::PRIORITES)),
            'echeance' => 'nullable|date',
            'statut' => 'required|in:'.implode(',', array_keys(Recommandation::STATUTS)),
            'taux_execution' => 'required|numeric|min:0|max:100',
            'observations' => 'nullable|string',
        ]);
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = Recommandation::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('REC-%d-%03d', $annee, $seq);
    }

    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::actif()->orderBy('libelle')->get(),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
            'sources' => Recommandation::SOURCES,
            'categories' => Recommandation::CATEGORIES,
            'priorites' => Recommandation::PRIORITES,
            'statuts' => Recommandation::STATUTS,
        ];
    }
}
