<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrevisionRecetteRequest;
use App\Models\BudgetRubrique;
use App\Models\CategorieRecette;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\PrevisionRecette;
use App\Models\SourceFinancement;
use App\Services\Recettes\GestionRecettesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PrevisionRecetteController extends Controller
{
    public function __construct(private readonly GestionRecettesService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PrevisionRecette::class);

        $previsions = PrevisionRecette::query()
            ->with(['exercice', 'categorie', 'sourceFinancement', 'etatMembre'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->boolean('courantes', true), fn ($q) => $q->courante())
            ->orderByDesc('exercice_id')
            ->orderBy('reference')
            ->paginate(20)
            ->withQueryString();

        return view('recettes.previsions.index', [
            'previsions' => $previsions,
            'exercices' => Exercice::orderByDesc('annee')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PrevisionRecette::class);

        return view('recettes.previsions.create', $this->options() + [
            'prevision' => new PrevisionRecette(['scenario' => 'initial', 'statut' => 'brouillon']),
        ]);
    }

    public function store(PrevisionRecetteRequest $request): RedirectResponse
    {
        $this->authorize('create', PrevisionRecette::class);

        try {
            $prevision = $this->service->creerPrevision(
                $request->validated() + ['cree_par' => $request->user()->id]
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('previsions-recettes.show', $prevision)->with('succes', 'Prévision de recette créée.');
    }

    public function show(PrevisionRecette $prevision): View
    {
        $this->authorize('view', $prevision);

        $prevision->load(['exercice', 'categorie', 'sourceFinancement', 'etatMembre', 'rubrique', 'creePar', 'validePar', 'recette']);

        return view('recettes.previsions.show', compact('prevision'));
    }

    public function edit(PrevisionRecette $prevision): View
    {
        $this->authorize('update', $prevision);

        return view('recettes.previsions.edit', $this->options() + compact('prevision'));
    }

    public function update(PrevisionRecetteRequest $request, PrevisionRecette $prevision): RedirectResponse
    {
        $this->authorize('update', $prevision);

        $prevision->update($request->validated());

        return redirect()->route('previsions-recettes.show', $prevision)->with('succes', 'Prévision mise à jour.');
    }

    public function reviser(Request $request, PrevisionRecette $prevision): RedirectResponse
    {
        $this->authorize('reviser', $prevision);

        $data = $request->validate([
            'montant_revise' => ['required', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $nouvelle = $this->service->reviserPrevision($prevision, (float) $data['montant_revise'], $data['observations'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('previsions-recettes.show', $nouvelle)->with('succes', 'Prévision révisée (nouvelle version).');
    }

    public function destroy(PrevisionRecette $prevision): RedirectResponse
    {
        $this->authorize('delete', $prevision);

        try {
            $prevision->delete();
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('previsions-recettes.index')->with('succes', 'Prévision supprimée.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'categories' => CategorieRecette::actif()->orderBy('ordre')->get(),
            'sources' => SourceFinancement::where('actif', true)->orderBy('libelle')->get(),
            'etats' => EtatMembre::orderBy('pays')->get(),
            'rubriques' => BudgetRubrique::orderBy('code')->get(),
        ];
    }
}
