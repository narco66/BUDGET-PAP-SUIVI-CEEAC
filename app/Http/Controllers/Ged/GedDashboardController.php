<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Services\Ged\GedDashboardService;
use Illuminate\View\View;

class GedDashboardController extends Controller
{
    public function __construct(private GedDashboardService $dashboard) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('ged.voir'), 403);

        return view('ged.dashboard', [
            'stats' => $this->dashboard->indicateurs(auth()->user()),
        ]);
    }
}
