<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Requests\Strategie\PontRbmRequest;
use App\Models\Pilier;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\ProgrammeStrategique;
use App\Services\Strategie\StrategieAlignementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StrategiePontRbmController extends Controller
{
    public function __construct(private readonly StrategieAlignementService $alignement)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', ProgrammeStrategique::class);

        $piliers = Pilier::with(['exercice', 'orientationStrategique.planStrategique'])
            ->orderBy('code')
            ->paginate(25);

        return view('strategie.pont-rbm.index', [
            'piliers' => $piliers,
            'synthese' => $this->alignement->syntheseAlignement(),
            'orientations' => OrientationStrategique::with('planStrategique')->orderBy('code')->get(),
            'programmes' => ProgrammeStrategique::orderBy('code')->get(['id', 'code', 'intitule', 'pilier_id']),
        ]);
    }

    public function update(PontRbmRequest $request, Pilier $pilier): RedirectResponse
    {
        abort_unless($request->user()->can('strategie.update'), 403);

        $this->alignement->rattacherPilierOrientation(
            $pilier,
            $request->input('orientation_strategique_id'),
            $request->input('programme_strategique_id')
        );

        return back()->with('succes', 'Pont RBM mis à jour pour le pilier '.$pilier->code.'.');
    }
}
