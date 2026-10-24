<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pour un jeton Sanctum : exige au moins une ability.
 * Pour une session SPA (cookie, sans PAT) : laisse passer ; les Policies restent applicables.
 */
class EnsureApiAbilityOrSession
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.', 'code' => 'unauthenticated'], 401);
        }

        $token = $user->currentAccessToken();

        if ($token === null || ! is_object($token) || ! method_exists($token, 'can')) {
            return $next($request);
        }

        foreach ($abilities as $ability) {
            if ($token->can($ability)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Scope insuffisant pour cette ressource.',
            'code' => 'forbidden_ability',
        ], 403);
    }
}
