<?php

namespace App\Http\Middleware;

use App\Services\Auth\MfaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Redirige les comptes sensibles sans MFA vers l'écran d'activation. */
class ForceMfaSetup
{
    private const AUTORISEES = [
        'mfa.setup', 'mfa.setup.confirm', 'mfa.disable',
        'password.edit', 'password.update', 'logout', 'login', 'mfa.challenge', 'mfa.challenge.verify',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $mfa = app(MfaService::class);
        $user = Auth::user();

        if (! $user || ! $mfa->estActive() || $mfa->aMfaActive($user) || ! $mfa->estObligatoire($user)) {
            return $next($request);
        }

        if ($request->routeIs(self::AUTORISEES) || $request->is('api/*')) {
            return $next($request);
        }

        return redirect()->route('mfa.setup')
            ->with('erreur', 'L\'authentification à deux facteurs est obligatoire pour votre profil.');
    }
}
