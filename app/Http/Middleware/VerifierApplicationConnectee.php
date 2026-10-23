<?php

namespace App\Http\Middleware;

use App\Models\ConnectedApp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Vérifie qu'une application connectée liée au jeton est toujours active (I-P1). */
class VerifierApplicationConnectee
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token?->connected_app_id) {
            $app = ConnectedApp::find($token->connected_app_id);

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
