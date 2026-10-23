<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\ProgrammationAnnuelleRequest;
use App\Models\Exercice;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProgrammationPluriannuelle;
use App\Services\Strategie\StrategiePapLiaisonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgrammationAnnuelleController extends Controller
{
    use FiltreStrategieIndex;

    public function __construct(private readonly StrategiePapLiaisonService $papLiaison)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProgrammationAnnuelle::class);

        $programmations = $this->appliquerFiltresIndex(
            ProgrammationAnnuelle::with('programmationPluriannuelle.planStrategique', 'exercice', 'pap')->orderByDesc('id'),
            $request
        )->paginate(20)->withQueryString();

        return view('strategie.programmations-annuelles.index', compact('programmations'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProgrammationAnnuelle::class);

        return view('strategie.programmations-annuelles.create', [
            'programmation' => new ProgrammationAnnuelle(['programmation_pluriannuelle_id' => $request->integer('pluri')]),
            'pluriannuelles' => ProgrammationPluriannuelle::with('planStrategique')->orderByDesc('annee_debut')->get(),
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee']),
        ]);
    }

    public function store(ProgrammationAnnuelleRequest $request): RedirectResponse
    {
        $this->authorize('create', ProgrammationAnnuelle::class);

        $programmation = ProgrammationAnnuelle::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->papLiaison->lierProgrammation($programmation, $request->integer('pap_id') ?: null);

        return redirect()->route('strategie.programmations-annuelles.show', $programmation)->with('succes', 'Programmation annuelle créée.');
    }

    public function show(ProgrammationAnnuelle $programmationAnnuelle): View
    {
        $this->authorize('view', $programmationAnnuelle);
        $programmationAnnuelle->load([
            'programmationPluriannuelle.planStrategique',
            'exercice',
            'pap.exercice',
        ]);
        $reconciliation = $this->papLiaison->reconciliation($programmationAnnuelle->exercice_id);

        return view('strategie.programmations-annuelles.show', [
            'programmation' => $programmationAnnuelle,
            'reconciliation' => $reconciliation,
        ]);
    }

    public function edit(ProgrammationAnnuelle $programmationAnnuelle): View
    {
        $this->authorize('update', $programmationAnnuelle);

        return view('strategie.programmations-annuelles.edit', [
            'programmation' => $programmationAnnuelle,
            'pluriannuelles' => ProgrammationPluriannuelle::with('planStrategique')->orderByDesc('annee_debut')->get(),
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee']),
        ]);
    }

    public function update(ProgrammationAnnuelleRequest $request, ProgrammationAnnuelle $programmationAnnuelle): RedirectResponse
    {
        $this->authorize('update', $programmationAnnuelle);
        $programmationAnnuelle->update($request->validated() + ['updated_by' => auth()->id()]);
        $this->papLiaison->lierProgrammation($programmationAnnuelle, $request->integer('pap_id') ?: null);

        return redirect()->route('strategie.programmations-annuelles.show', $programmationAnnuelle)->with('succes', 'Programmation mise à jour.');
    }

    public function destroy(ProgrammationAnnuelle $programmationAnnuelle): RedirectResponse
    {
        $this->authorize('delete', $programmationAnnuelle);
        $programmationAnnuelle->delete();

        return redirect()->route('strategie.programmations-annuelles.index')->with('succes', 'Programmation annuelle supprimée.');
    }
}
