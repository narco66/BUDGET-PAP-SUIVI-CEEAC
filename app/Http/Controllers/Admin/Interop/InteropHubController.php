<?php

namespace App\Http\Controllers\Admin\Interop;

use App\Http\Controllers\Controller;
use App\Models\ConnectedApp;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Portail interopérabilité — tableau de bord MOA (I-P1). */
class InteropHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('api.view') || $request->user()->can('api.admin'), 403);

        $stats = [
            'apps_actives' => ConnectedApp::where('statut', 'actif')->count(),
            'apps_total' => ConnectedApp::count(),
            'clients_actifs' => \App\Models\ApiClient::where('statut', 'actif')->count(),
            'appels_24h' => \App\Models\ApiLog::where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('admin.interoperabilite.index', compact('stats'));
    }
}
