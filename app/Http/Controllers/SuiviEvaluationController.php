<?php

namespace App\Http\Controllers;

use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Services\Budget\ExerciceService;
use App\Services\Suivi\SuiviEvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hub Suivi-Évaluation — pilotage S&E institutionnel (phases P0 → P3). */
class SuiviEvaluationController extends Controller
{
    public function __construct(private readonly SuiviEvaluationService $service)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($this->service->peutConsulter($request->user()), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice')) ?? ExerciceService::courant();
        $data = $exercice ? $this->service->hub($exercice->id) : null;

        return view('suivi-evaluation.index', compact('exercices', 'exercice', 'data'));
    }

    public function showEvaluation(Request $request, EvaluationPeriodique $evaluation): View
    {
        abort_unless($this->service->peutConsulter($request->user()), 403);
        $evaluation->load(['exercice', 'campagne', 'generePar', 'recommandations', 'missions', 'plans']);

        return view('suivi-evaluation.evaluations.show', compact('evaluation'));
    }

    public function generer(Request $request): RedirectResponse
    {
        abort_unless($this->service->peutGenerer($request->user()), 403);

        $exercice = Exercice::findOrFail($request->integer('exercice'));
        $campagneId = $request->filled('campagne') ? $request->integer('campagne') : null;

        $this->service->genererEvaluation($exercice->id, $campagneId, $request->user()->id);

        return back()->with('succes', 'Évaluation périodique générée.');
    }

    public function genererImpact(Request $request): RedirectResponse
    {
        abort_unless($this->service->peutGenerer($request->user()), 403);

        $exercice = Exercice::findOrFail($request->integer('exercice'));
        $this->service->genererEvaluationImpact($exercice->id, $request->user()->id);

        return back()->with('succes', 'Évaluation d’impact générée.');
    }
}
