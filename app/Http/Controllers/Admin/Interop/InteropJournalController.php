<?php

namespace App\Http\Controllers\Admin\Interop;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\ConnectedApp;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consultation du journal des échanges API (I-P1). */
class InteropJournalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('api.monitor') || $request->user()->can('api.admin'), 403);

        $logs = ApiLog::with(['user'])
            ->when($request->filled('chemin'), fn ($q) => $q->where('chemin', 'like', '%'.$request->chemin.'%'))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', (int) $request->statut))
            ->when($request->filled('statut_metier'), fn ($q) => $q->where('statut_metier', $request->statut_metier))
            ->when($request->filled('connected_app_id'), fn ($q) => $q->where('connected_app_id', $request->connected_app_id))
            ->when($request->filled('depuis'), fn ($q) => $q->whereDate('created_at', '>=', $request->depuis))
            ->latest('created_at')
            ->paginate(40)
            ->withQueryString();

        $apps = ConnectedApp::orderBy('code')->get(['id', 'code', 'libelle']);

        return view('admin.interoperabilite.journal.index', [
            'logs' => $logs,
            'apps' => $apps,
            'page' => 'journal',
        ]);
    }
}
