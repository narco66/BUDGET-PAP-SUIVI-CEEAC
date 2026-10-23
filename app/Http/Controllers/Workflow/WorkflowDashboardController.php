<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Services\Workflow\WorkflowDashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Tableau de bord performance workflow (P2). */
class WorkflowDashboardController extends Controller
{
    public function __construct(
        private readonly WorkflowDashboardService $dashboard,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('workflow.view'), 403);

        $jours = (int) $request->input('jours', 90);

        return view('workflow.dashboard.index', [
            'data' => $this->dashboard->tableau($jours),
            'jours' => $jours,
        ]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('workflow.audit'), 403);

        $jours = (int) $request->input('jours', 90);
        $lignes = $this->dashboard->exportCsvLignes($jours);

        $csv = "date;circuit;action;etape;acteur;objet;id;observation\n";
        foreach ($lignes as $row) {
            $csv .= implode(';', array_map(fn ($v) => str_replace(';', ',', (string) ($v ?? '')), $row))."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit-workflow.csv"',
        ]);
    }
}
