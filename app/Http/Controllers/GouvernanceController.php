<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Services\Gouvernance\GouvernanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cockpit de gouvernance, contrôle interne & conformité (Lot D).
 * Vue consolidée : conformité par domaine (8 services d'audit), risques, piste d'audit. Lecture seule.
 */
class GouvernanceController extends Controller
{
    public function cockpit(Request $request, GouvernanceService $service): View
    {
        abort_unless($request->user()->can('audit.voir'), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? $exercices->firstWhere('statut', 'ouvert')
            ?? $exercices->first();
        $exerciceId = $exercice?->id ?? 0;

        $conformite = $service->conformiteParDomaine($exerciceId);

        return view('gouvernance.cockpit', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'conformite' => $conformite,
            'scoreGlobal' => $service->scoreGlobal($conformite),
            'risques' => $service->syntheseRisques(),
            'missions' => $service->missionsAudit(),
            'coso' => $service->controleInterne(),
            'conformiteStats' => $service->conformite(),
            'audit' => $service->pisteAudit(),
        ]);
    }
}
