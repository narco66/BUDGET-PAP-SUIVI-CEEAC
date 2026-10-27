<?php

namespace App\Http\Middleware;

use App\Support\AuditContexte;
use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attribue à chaque requête un identifiant (repris de X-Request-Id s'il est valide) porté par tous
 * les événements du journal qu'elle produit, et renvoyé dans la réponse pour corrélation (JNL-009).
 */
class IdentifiantRequete
{
    public function handle(Request $request, Closure $next): Response
    {
        AuditContexte::reinitialiser();
        AuditLogger::reinitialiser();
        $id = AuditContexte::definirRequete($request->headers->get('X-Request-Id'));

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
