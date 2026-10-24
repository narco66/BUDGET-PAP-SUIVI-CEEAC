<?php

namespace App\Http\Middleware;

use App\Models\ConnectedApp;
use App\Support\SanctumContexte;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Vérifie qu'une application connectée liée au jeton est toujours active (I-P1). */
class VerifierApplicationConnectee
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        $connectedAppId = SanctumContexte::connectedAppId($token);

        if ($connectedAppId) {
            $app = ConnectedApp::find($connectedAppId);

            if (! $app || ! $app->estActive()) {
                return response()->json([
                    'message' => 'Application connectée suspendue ou révoquée.',
                ], 403);
            }

            $request->attributes->set('connected_app_id', $app->id);
        }

        return $next($request);
    }
}
