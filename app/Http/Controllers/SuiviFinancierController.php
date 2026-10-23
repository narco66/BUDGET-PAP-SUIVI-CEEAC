<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Services\Budget\ExerciceService;
use App\Services\Budget\SituationBudgetaireService;
use App\Services\Budget\SuiviFinancierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hub Suivi Financier — pilotage budgétaire institutionnel (phase P2). */
class SuiviFinancierController extends Controller
{
    public function __construct(
        private readonly SuiviFinancierService $service,
        private readonly SituationBudgetaireService $situations,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->service->peutConsulter($request->user()), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice')) ?? ExerciceService::courant();
        $data = $exercice ? $this->service->hub($exercice->id) : null;

        return view('suivi-financier.index', compact('exercices', 'exercice', 'data'));
    }

    public function anomalies(Request $request): View
    {
        abort_unless($this->service->peutConsulter($request->user()), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice')) ?? ExerciceService::courant();
        $audit = $exercice ? app(\App\Services\Budget\CompteurMouvementAuditService::class)->analyser($exercice->id) : null;

        return view('suivi-financier.anomalies', compact('exercices', 'exercice', 'audit'));
    }

    public function capturerSituation(Request $request): RedirectResponse
    {
        abort_unless($this->service->peutCapturer($request->user()), 403);

        $exercice = Exercice::findOrFail($request->integer('exercice'));
        $this->situations->capturer($exercice->id, $request->user()->id);

        return back()->with('succes', 'Situation budgétaire capturée.');
    }
}
