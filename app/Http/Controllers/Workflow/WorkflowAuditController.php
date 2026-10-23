<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\AuditWorkflow;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Journal MOA des actions workflow (P1). */
class WorkflowAuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('workflow.audit'), 403);

        $journaux = AuditWorkflow::query()
            ->with('acteur')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('workflow'), fn ($q) => $q->where('workflow_code', $request->workflow))
            ->latest('created_at')
            ->paginate(40)
            ->withQueryString();

        $actions = AuditWorkflow::query()->distinct()->orderBy('action')->pluck('action');
        $circuits = AuditWorkflow::query()->distinct()->orderBy('workflow_code')->pluck('workflow_code');

        return view('workflow.audit.index', compact('journaux', 'actions', 'circuits'));
    }
}
