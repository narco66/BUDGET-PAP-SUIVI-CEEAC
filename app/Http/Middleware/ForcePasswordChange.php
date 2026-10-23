<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Force le changement de mot de passe (1ʳᵉ connexion / réinitialisation) avant tout autre accès. */
class ForcePasswordChange
{
    /** Routes accessibles même avec un mot de passe à renouveler. */
    private const AUTORISEES = ['password.edit', 'password.update', 'logout', 'mfa.setup', 'mfa.setup.confirm', 'mfa.disable'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->doit_changer_mot_de_passe
            && ! $request->routeIs(self::AUTORISEES)
            && ! $request->is('api/*')) {
            return redirect()->route('password.edit')
                ->with('erreur', 'Vous devez définir un nouveau mot de passe avant de continuer.');
        }

        return $next($request);
    }
}
