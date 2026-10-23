<?php

namespace App\Http\Controllers\Admin\Interop;

use App\Http\Controllers\Controller;
use App\Models\ApiMetric;
use App\Services\Interop\ApiMonitoringService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Dashboard monitoring API — KPI trafic, erreurs, latence (I-P3). */
class InteropMonitoringController extends Controller
{
    public function index(Request $request, ApiMonitoringService $monitoring): View
    {
        abort_unless($request->user()->can('api.monitor') || $request->user()->can('api.admin'), 403);

        $depuis = now()->subDays((int) $request->integer('jours', 1));

        return view('admin.interoperabilite.monitoring.index', [
            'page' => 'monitoring',
            'kpis' => $monitoring->kpis($depuis),
            'topChemins' => $monitoring->topChemins(10, $depuis),
            'parApp' => $monitoring->parApplication($depuis),
            'metriques' => ApiMetric::latest('jour')->limit(14)->get(),
            'jours' => (int) $request->integer('jours', 1),
        ]);
    }
}
