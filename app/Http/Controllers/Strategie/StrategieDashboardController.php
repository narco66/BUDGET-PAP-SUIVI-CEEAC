<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Services\Strategie\StrategiePilotageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StrategieDashboardController extends Controller
{
    public function __construct(private readonly StrategiePilotageService $pilotage)
    {
    }

    public function decisionnel(Request $request): View
    {
        $this->authorize('viewAny', PlanStrategique::class);

        $exerciceId = $request->integer('exercice') ?: null;
        $tableau = $this->pilotage->tableauDecisionnel($exerciceId);

        return view('strategie.dashboard.decisionnel', [
            'tableau' => $tableau,
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee', 'statut']),
            'exerciceId' => $exerciceId ?? $tableau['exercice']?->id,
        ]);
    }

    public function pdf(Request $request): Response
    {
        abort_unless($request->user()->can('strategie.export'), 403);

        $exerciceId = $request->integer('exercice') ?: null;
        $tableau = $this->pilotage->tableauDecisionnel($exerciceId);

        $pdf = Pdf::loadView('strategie.pdf.pilotage', compact('tableau'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('tableau-pilotage-strategique-'.now()->format('Ymd').'.pdf');
    }
}
