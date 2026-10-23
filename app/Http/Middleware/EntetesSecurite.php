<?php

namespace App\Http\Middleware;

use App\Services\Security\CspPolicyBuilder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP (Lot T4 / P3-4 — durcissement CSP, OWASP A05).
 * Politique externalisée dans config/security.php ; nonce partagé aux vues et Vite.
 */
class EntetesSecurite
{
    public function __construct(private readonly CspPolicyBuilder $csp) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.headers.enabled', true)) {
            return $next($request);
        }

        $nonce = $this->genererNonce();
        $request->attributes->set('csp_nonce', $nonce);
        View::share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        if ($response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        $this->appliquerEntetes($request, $response, $nonce);

        return $response;
    }

    private function genererNonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    private function appliquerEntetes(Request $request, Response $response, string $nonce): void
    {
        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');

        if (config('security.csp.enabled', true)) {
            $h->set('Content-Security-Policy', $this->csp->enforce($request, $nonce));

            if (config('security.csp.report_only', false)) {
                $h->set('Content-Security-Policy-Report-Only', $this->csp->reportOnly($request, $nonce));
            }
        }

        if ($request->secure() && config('security.hsts.enabled', true)) {
            $max = config('security.hsts.max_age', 31536000);
            $hsts = "max-age={$max}";
            if (config('security.hsts.include_subdomains', true)) {
                $hsts .= '; includeSubDomains';
            }
            $h->set('Strict-Transport-Security', $hsts);
        }
    }
}
