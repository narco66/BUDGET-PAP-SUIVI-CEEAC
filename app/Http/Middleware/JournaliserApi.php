<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Support\ApiJournalHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Journalise chaque appel API avec empreinte requête (D12 — EF-D12-06, I-P0). */
class JournaliserApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $debut = microtime(true);
        $response = $next($request);
        $statut = $response->getStatusCode();

        ApiLog::create([
            'user_id' => $request->user()?->id,
            'token_nom' => $request->user()?->currentAccessToken()?->name,
            'connected_app_id' => $request->attributes->get('connected_app_id')
                ?? $request->user()?->currentAccessToken()?->connected_app_id,
            'methode' => $request->method(),
            'chemin' => substr($request->path(), 0, 500),
            'request_hash' => ApiJournalHelper::hashRequete($request),
            'statut' => $statut,
            'statut_metier' => ApiJournalHelper::statutMetier($statut),
            'duree_ms' => (int) round((microtime(true) - $debut) * 1000),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
            'created_at' => now(),
        ]);

        return $response;
    }
}
