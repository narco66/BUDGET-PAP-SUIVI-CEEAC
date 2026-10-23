<?php

namespace App\Services\Security;

use App\Support\ReverbClientConfig;
use Illuminate\Http\Request;

/** Construction des politiques CSP (P3-4 — durcissement OWASP A05). */
class CspPolicyBuilder
{
    /** Politique appliquée (enforce). */
    public function enforce(Request $request, ?string $nonce = null): string
    {
        return $this->compile($this->directives($request, $nonce, config('security.csp.mode', 'standard')));
    }

    /** Politique stricte en Report-Only (surveillance progressive). */
    public function reportOnly(Request $request, ?string $nonce = null): string
    {
        $directives = $this->directives($request, $nonce, 'strict_report');
        $directives['report-uri'] = [$this->reportUri()];

        return $this->compile($directives);
    }

    public function reportUri(): string
    {
        return config('security.csp.report_uri') ?: route('csp.report', [], false);
    }

    /** @return array<string, list<string>> */
    private function directives(Request $request, ?string $nonce, string $mode): array
    {
        $cdn = config('security.csp.cdn', ['https://cdn.jsdelivr.net']);
        $fonts = config('security.csp.fonts', ['https://fonts.bunny.net']);
        $override = $this->routeOverride($request);

        $script = array_merge(["'self'"], $cdn, $override['script'] ?? []);
        $style = array_merge(["'self'"], $cdn, $override['style'] ?? []);
        $connect = array_merge(["'self'"], $override['connect'] ?? []);
        $font = array_merge(["'self'", 'data:'], $cdn, $fonts);

        if ($nonce) {
            $script[] = "'nonce-{$nonce}'";
        }

        // Alpine.js (build standard, chargé via Vite) évalue ses directives (x-data/@click/x-for…)
        // avec new Function() → nécessite 'unsafe-eval'. Sans cela les composants Alpine ne réagissent
        // pas (boutons dynamiques inertes). Désactivable si l'on migre vers le build CSP d'Alpine.
        if (config('security.csp.allow_eval', true)) {
            $script[] = "'unsafe-eval'";
        }

        if ($mode === 'standard') {
            $script[] = "'unsafe-inline'";
            $style[] = "'unsafe-inline'";
        } elseif ($mode === 'strict') {
            // Transition : nonce Vite + inline legacy encore toléré.
            $script[] = "'unsafe-inline'";
            $style[] = "'unsafe-inline'";
        }
        // mode strict_report (Report-Only) : pas de unsafe-inline → signale les inline restants.

        if ($this->viteDevEnabled()) {
            $viteHost = 'http://localhost:5173';
            $viteWs = 'ws://localhost:5173';
            $script[] = $viteHost;
            $connect[] = $viteHost;
            $connect[] = $viteWs;
            $font[] = $fonts[0] ?? 'https://fonts.bunny.net';
        }

        if (config('app.env') === 'production') {
            $connect[] = 'https:';
        }

        $connect = array_merge($connect, ReverbClientConfig::cspConnectSources());

        return [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'self'"],
            'frame-src' => ["'self'"],
            'worker-src' => ["'self'", 'blob:'],
            'script-src' => $this->unique($script),
            'style-src' => $this->unique($style),
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => $this->unique($font),
            'connect-src' => $this->unique($connect),
            ...(config('app.env') === 'production' ? ['upgrade-insecure-requests' => []] : []),
        ];
    }

    /** @return array{script?: list<string>, style?: list<string>, connect?: list<string>} */
    private function routeOverride(Request $request): array
    {
        $route = $request->route()?->getName();
        if ($route === null) {
            return [];
        }

        foreach (config('security.csp.route_overrides', []) as $pattern => $sources) {
            if ($route === $pattern || str_ends_with($route, '.'.$pattern)) {
                return $sources;
            }
        }

        return [];
    }

    private function viteDevEnabled(): bool
    {
        return config('security.csp.vite_dev', true)
            && config('app.env') !== 'production'
            && ! app()->runningUnitTests();
    }

    /** @param array<string, list<string>> $directives */
    private function compile(array $directives): string
    {
        $parts = [];
        foreach ($directives as $name => $values) {
            $parts[] = $values === [] ? $name : $name.' '.implode(' ', $values);
        }

        return implode('; ', $parts);
    }

    /** @param list<string> $values */
    private function unique(array $values): array
    {
        return array_values(array_unique($values));
    }
}
