<?php

return [

    /** En-têtes HTTP de sécurité (Lot T4 / P3-4). Désactivables via APP_SECURITY_HEADERS=false. */
    'headers' => [
        'enabled' => env('APP_SECURITY_HEADERS', true),
    ],

    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        /** standard : compatibilité (unsafe-inline) · strict : nonce Vite + directives durcies */
        'mode' => env('CSP_MODE', 'standard'),
        /** En-tête Report-Only parallèle (surveillance sans blocage). */
        'report_only' => env('CSP_REPORT_ONLY', false),
        /** 'unsafe-eval' requis par Alpine.js (build standard) ; false si migration vers @alpinejs/csp. */
        'allow_eval' => env('CSP_ALLOW_EVAL', true),
        /** URI de collecte des violations (défaut : route interne csp.report). */
        'report_uri' => env('CSP_REPORT_URI'),
        'cdn' => [
            'https://cdn.jsdelivr.net',
        ],
        'fonts' => [
            'https://fonts.bunny.net',
        ],
        /** Routes nommées → sources script/style/connect supplémentaires. */
        'route_overrides' => [
            'api.docs' => [
                'script' => ['https://unpkg.com'],
                'style' => ['https://unpkg.com'],
                'connect' => ['https://unpkg.com'],
            ],
            'reporting.cockpit' => [
                'connect' => [], // complété dynamiquement (Reverb ws/wss) dans CspPolicyBuilder
            ],
        ],
        'vite_dev' => env('CSP_VITE_DEV', true),
    ],

    'hsts' => [
        'enabled' => env('HSTS_ENABLED', true),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
    ],

];
