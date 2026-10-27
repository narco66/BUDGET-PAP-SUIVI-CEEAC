<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Compression gzip des pages HTML et des réponses JSON quand le navigateur l'accepte. Les grandes vues (arbre RBM,
 * PAP, matrices de droits) pèsent plusieurs centaines de Ko à plus d'un Mo : compressées, elles en pèsent ~10 fois
 * moins. Sans effet sur les téléchargements, les flux et les réponses déjà encodées (un serveur qui compresse lui-même
 * ne recompresse pas une réponse portant déjà « Content-Encoding »).
 */
class CompresserReponse
{
    private const TYPES = ['text/html', 'application/json', 'text/plain', 'text/csv', 'application/xml', 'text/xml'];

    private const TAILLE_MIN = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('performance.compression.enabled', true)
            || ! function_exists('gzencode')
            || ! str_contains((string) $request->header('Accept-Encoding'), 'gzip')
            || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse
            || $response->headers->has('Content-Encoding')
            || $request->isMethod('HEAD')
            || ! $this->typeCompressible((string) $response->headers->get('Content-Type'))) {
            return $response;
        }

        $contenu = $response->getContent();
        if ($contenu === false || strlen($contenu) < self::TAILLE_MIN) {
            return $response;
        }

        $compresse = gzencode($contenu, (int) config('performance.compression.niveau', 5));
        if ($compresse === false) {
            return $response;
        }

        $response->setContent($compresse);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compresse));
        $response->setVary(array_unique(array_merge($response->getVary(), ['Accept-Encoding'])));

        return $response;
    }

    private function typeCompressible(string $type): bool
    {
        $type = strtolower(trim(explode(';', $type)[0]));

        return in_array($type, self::TYPES, true);
    }
}
