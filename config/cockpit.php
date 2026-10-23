<?php

return [
    /** Mises à jour live du cockpit exécutif (P3-7). */
    'live' => [
        'enabled' => (bool) env('COCKPIT_LIVE_ENABLED', true),
        /** Intervalle de polling (secondes) si WebSocket indisponible. */
        'poll_seconds' => (int) env('COCKPIT_LIVE_POLL_SECONDS', 15),
        /** Reverb / Laravel Echo (prod) — activé si BROADCAST_CONNECTION=reverb et clé présente. */
        'reverb' => [
            'enabled' => (bool) env('REVERB_ENABLED', false),
            'host' => env('REVERB_HOST', 'localhost'),
            'port' => (int) env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],
    ],
];
