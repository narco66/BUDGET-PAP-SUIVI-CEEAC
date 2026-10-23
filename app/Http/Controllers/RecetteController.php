<?php

namespace App\Http\Controllers;

use App\Models\BudgetRubrique;
use App\Models\CategorieRecette;
use App\Models\Exercice;
use App\Models\Recette;
use App\Services\Recettes\GestionRecettesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecetteController extends Controller
{
    public function __construct(private readonly GestionRecettesService $service)
    {
    }

    /** Tableau de bord des recettes : synthèse prévu/réalisé + liste filtrable. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Recette::class);

        $exercice = $this->exerciceCourant($request);

        $synthese = $exercice
            ? $this->service->syntheseExercice($exercice->id)
            : ['previsions' => 0, 'recettes' => 0, 'montant_prevu' => 0.0, 'montant_realise' => 0.0];

        $recettes = Recette::query()
            ->with(['categorie', 'sourceFinancement', 'etatMembre', 'rubrique'])
            ->when($exercice, fn ($q) => $q->where('exercice_id', $exercice->id))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('categorie'), fn ($q) => $q->where('categorie_recette_id', $request->categorie))
            ->orderByDesc('montant_realise')
            ->paginate(20)
            ->withQueryString();

        $parCategorie = Recette::query()
            ->selectRaw('categorie_recette_id, SUM(montant_prevu) prevu, SUM(montant_realise) realise')
            ->when($exercice, fn ($q) => $q->where('exercice_id', $exercice->id))
            ->groupBy('categorie_recette_id')
            ->with('categorie')
            ->get();

        return view('recettes.index', [
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'synthese' => $synthese,
            'recettes' => $recettes,
            'parCategorie' => $parCategorie,
            'categories' => CategorieRecette::actif()->orderBy('ordre')->get(),
        ]);
    }

    public function show(Recette $recette): View
    {
        $this->authorize('view', $recette);

        $recette->load(['exercice', 'prevision', 'categorie', 'sourceFinancement', 'etatMembre', 'rubrique', 'structure']);

        return view('recettes.show', [
            'recette' => $recette,
            'rubriques' => BudgetRubrique::orderBy('code')->get(['id', 'code', 'intitule']),
        ]);
    }

    /** Ventilation budgétaire : rattache (ou corrige) la rubrique d'imputation d'une recette. */
    public function ventiler(Request $request, Recette $recette): RedirectResponse
    {
        $this->authorize('update', $recette);

        $data = $request->validate([
            'rubrique_id' => ['required', 'exists:budget_rubriques,id'],
        ]);

        $recette->update(['rubrique_id' => $data['rubrique_id']]);

        return back()->with('succes', 'Recette ventilée sur la rubrique budgétaire.');
    }

    private function exerciceCourant(Request $request): ?Exercice
    {
        if ($request->filled('exercice')) {
            return Exercice::find($request->exercice);
        }

        return Exercice::actif()->first() ?? Exercice::orderByDesc('annee')->first();
    }
}
