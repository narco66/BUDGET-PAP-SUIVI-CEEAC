<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Models\Strategie\PlanStrategique;
use App\Services\Strategie\StrategieCoherenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategieRapportController extends Controller
{
    public function __construct(private readonly StrategieCoherenceService $coherence)
    {
    }

    public function coherence(Request $request): View
    {
        $this->authorize('viewAny', PlanStrategique::class);

        $planId = $request->integer('plan') ?: null;
        $rapport = $this->coherence->rapport($planId);

        return view('strategie.rapport.coherence', [
            'rapport' => $rapport,
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'planId' => $planId,
        ]);
    }
}
