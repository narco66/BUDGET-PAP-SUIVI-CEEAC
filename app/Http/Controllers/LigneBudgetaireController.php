<?php

namespace App\Http\Controllers;

use App\Http\Requests\LigneBudgetaireRequest;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Services\Budget\LigneCreditsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class LigneBudgetaireController extends Controller
{
    public function __construct(private readonly LigneCreditsService $credits) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LigneBudgetaire::class);

        $exercice = $this->credits->exercice($request->filled('exercice') ? $request->integer('exercice') : null);
        $base = $this->credits->requete($request->user(), $exercice?->id, $request);
        $lignes = (clone $base)
            ->with('rubrique', 'structure', 'sourceFinancement')
            ->orderBy('code')->paginate(25)->withQueryString();

        return view('budget.index', [
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'enveloppe' => $this->credits->enveloppe($exercice?->id),
            'lignesVerrouillees' => $this->credits->lignesVerrouillees($exercice?->id),
            'lignes' => $lignes,
            'totaux' => $this->credits->totaux($base),
            'parNature' => $this->credits->syntheseParNature($exercice?->id),
            'parStructure' => $this->credits->syntheseParStructure($exercice?->id),
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'libelle']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LigneBudgetaire::class);

        return view('budget.create', $this->credits->options($this->credits->exercice(
            $request->filled('exercice') ? $request->integer('exercice') : null
        )));
    }

    public function store(LigneBudgetaireRequest $request): RedirectResponse
    {
        $this->authorize('create', LigneBudgetaire::class);

        try {
            $this->credits->creer($request->validated(), $request);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('budget.index', ['exercice' => $request->exercice_id])
            ->with('succes', 'Ligne budgétaire créée.');
    }

    public function edit(LigneBudgetaire $ligne): View
    {
        $this->authorize('update', $ligne);

        return view('budget.edit', array_merge(['ligne' => $ligne], $this->credits->options($ligne->exercice), [
            'financementSources' => $this->credits->financementPourFormulaire($ligne),
        ]));
    }

    public function update(LigneBudgetaireRequest $request, LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('update', $ligne);

        try {
            $this->credits->mettreAJour($ligne, $request->validated(), $request);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('budget.index', ['exercice' => $ligne->exercice_id])
            ->with('succes', 'Ligne budgétaire mise à jour.');
    }

    public function destroy(LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('delete', $ligne);

        $exId = $ligne->exercice_id;
        try {
            $this->credits->supprimer($ligne);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('budget.index', ['exercice' => $exId])->with('succes', 'Ligne supprimée.');
    }
}
