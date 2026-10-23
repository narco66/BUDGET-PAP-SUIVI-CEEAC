<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('audit.voir'), 403);

        $journaux = JournalAudit::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->type))
            ->latest('created_at')
            ->paginate(40)->withQueryString();

        $actions = JournalAudit::query()->distinct()->pluck('action');
        $types = JournalAudit::query()->whereNotNull('auditable_type')->distinct()->pluck('auditable_type');

        return view('audit.index', compact('journaux', 'actions', 'types'));
    }
}
