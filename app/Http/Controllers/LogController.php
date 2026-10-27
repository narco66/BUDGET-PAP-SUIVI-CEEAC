<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Journal des connexions (le journal d'activité global est exposé par AuditController). */
class LogController extends Controller
{
    public function connexions(Request $request): View
    {
        // Journal de sécurité : réservé aux habilitations dédiées (audit.voir, détenue par presque tous les rôles, ne suffit plus).
        abort_unless($request->user()->can('utilisateur.consulter_journal') || $request->user()->can('audit.security.view'), 403);

        $logs = LoginLog::with('user')
            ->when($request->filled('email'), fn ($q) => $q->where('email', 'like', "%{$request->email}%"))
            ->when($request->filled('succes'), fn ($q) => $q->where('succes', $request->boolean('succes')))
            ->latest('created_at')->paginate(40)->withQueryString();

        return view('logs.connexions', compact('logs'));
    }
}
