<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Services\Rbm\IndicateurPerformanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hub pilotage Suivi des Indicateurs IOV (phase P1). */
class SuiviIndicateursController extends Controller
{
    public function __construct(private readonly IndicateurPerformanceService $performance)
    {
    }

    public function hub(Request $request): View
    {
        abort_unless($request->user()->can('indicateur.voir'), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? Exercice::firstWhere('statut', 'ouvert')
            ?? $exercices->first();

        $feux = $exercice ? $this->performance->feuxGlobaux($exercice->id) : ['vert' => 0, 'orange' => 0, 'rouge' => 0];
        $evolution = $exercice ? $this->performance->evolutionExercice($exercice->id) : collect();
        $performances = $exercice ? $this->performance->performancesExercice($exercice->id) : collect();
        $alertes = $evolution->where('feu', 'rouge')->take(10);

        return view('rbm.suivi-indicateurs.hub', compact('exercices', 'exercice', 'feux', 'evolution', 'alertes', 'performances'));
    }
}
