<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExerciceRequest;
use App\Models\Exercice;
use App\Services\Budget\ExerciceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ExerciceController extends Controller
{
    public function __construct(private readonly ExerciceService $exerciceService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Exercice::class);

        $exercices = Exercice::query()
            ->with('cloturePar')
            ->withCount(['lignesBudgetaires', 'piliers'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$request->q}%")
                ->orWhere('libelle', 'like', "%{$request->q}%")
                ->orWhere('annee', 'like', "%{$request->q}%")))
            ->orderByDesc('annee')
            ->paginate(15)
            ->withQueryString();

        return view('exercices.index', compact('exercices'));
    }

    public function create(): View
    {
        $this->authorize('create', Exercice::class);

        return view('exercices.create', ['exercice' => new Exercice(['devise' => 'XAF', 'statut' => 'brouillon'])]);
    }

    public function store(ExerciceRequest $request): RedirectResponse
    {
        $this->authorize('create', Exercice::class);

        $exercice = Exercice::create([
            ...$request->validated(),
            'statut' => 'brouillon',
            'est_actif' => false,
        ]);

        return redirect()->route('exercices.show', $exercice)->with('succes', 'Exercice budgétaire créé.');
    }

    public function show(Exercice $exercice): View
    {
        $this->authorize('view', $exercice);

        $exercice->load('cloturePar', 'pap');
        $exercice->loadCount(['lignesBudgetaires', 'piliers', 'contributions']);

        return view('exercices.show', compact('exercice'));
    }

    public function edit(Exercice $exercice): View
    {
        $this->authorize('update', $exercice);

        return view('exercices.edit', compact('exercice'));
    }

    public function update(ExerciceRequest $request, Exercice $exercice): RedirectResponse
    {
        $this->authorize('update', $exercice);

        $exercice->update($request->validated());

        return redirect()->route('exercices.show', $exercice)->with('succes', 'Exercice mis à jour.');
    }

    public function destroy(Exercice $exercice): RedirectResponse
    {
        $this->authorize('delete', $exercice);

        if ($exercice->lignesBudgetaires()->exists() || $exercice->piliers()->exists() || $exercice->pap()->exists()) {
            return back()->with('erreur', 'Suppression impossible : des données sont rattachées à cet exercice.');
        }

        $exercice->delete();

        return redirect()->route('exercices.index')->with('succes', 'Exercice supprimé.');
    }

    public function ouvrir(Exercice $exercice): RedirectResponse
    {
        $this->authorize('ouvrir', $exercice);

        try {
            $this->exerciceService->ouvrir($exercice, request()->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Exercice ouvert.');
    }

    public function cloturer(Exercice $exercice): RedirectResponse
    {
        $this->authorize('cloturer', $exercice);

        try {
            $this->exerciceService->cloturer($exercice, request()->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Exercice clôturé. Toute écriture budgétaire est désormais bloquée.');
    }

    public function activer(Exercice $exercice): RedirectResponse
    {
        $this->authorize('activer', $exercice);

        $this->exerciceService->definirActif($exercice, request()->user());

        return back()->with('succes', 'Exercice défini comme exercice de référence.');
    }
}
