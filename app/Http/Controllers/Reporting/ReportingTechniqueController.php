<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Services\Reporting\RapportTechniqueService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hub des rapports techniques (P3-2). */
class ReportingTechniqueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Rapport::class);
        abort_unless(
            $request->user()->can('reporting.admin') || $request->user()->can('admin_technique.view'),
            403
        );

        return view('reporting.techniques.index', [
            'types' => RapportTechniqueService::TYPES,
            'exercice' => $request->filled('exercice')
                ? Exercice::findOrFail($request->integer('exercice'))
                : (Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->first()),
            'exercices' => Exercice::orderByDesc('annee')->get(),
        ]);
    }
}
