<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Models\Strategie\PlanStrategique;
use App\Services\Strategie\StrategieAlignementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategieMatriceController extends Controller
{
    public function __construct(private readonly StrategieAlignementService $alignement)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PlanStrategique::class);

        $planId = $request->integer('plan') ?: null;
        $lignes = $this->alignement->matriceAlignement($planId);
        $synthese = $this->alignement->syntheseAlignement();

        return view('strategie.matrice.index', [
            'lignes' => $lignes,
            'synthese' => $synthese,
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'planId' => $planId,
        ]);
    }
}
