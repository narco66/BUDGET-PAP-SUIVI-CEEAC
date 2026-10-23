<?php

namespace App\Http\Controllers;

use App\Models\Structure;
use App\Services\Institution\InstitutionMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionController extends Controller
{
    public function index(Request $request, InstitutionMetricsService $metrics): View
    {
        $this->authorize('viewAny', Structure::class);

        $indicateurs = $metrics->indicateurs($request->integer('exercice') ?: null);

        return view('institution.dashboard', compact('indicateurs'));
    }
}
