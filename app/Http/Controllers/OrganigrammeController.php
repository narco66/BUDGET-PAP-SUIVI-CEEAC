<?php

namespace App\Http\Controllers;

use App\Models\Structure;
use App\Services\Institution\InstitutionMetricsService;
use Illuminate\View\View;

class OrganigrammeController extends Controller
{
    public function index(InstitutionMetricsService $metrics): View
    {
        $this->authorize('viewAny', Structure::class);

        $racines = Structure::with('enfantsRecursifs')
            ->whereNull('parent_id')
            ->orderBy('ordre')->get();

        $indicateurs = $metrics->indicateurs();

        return view('structures.organigramme', compact('racines', 'indicateurs'));
    }
}
