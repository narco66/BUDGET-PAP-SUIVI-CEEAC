<?php

return [

    'rate_limit' => [
        /** Tentatives max d'émission de jeton par minute et par IP. */
        'token_per_minute' => (int) env('API_TOKEN_RATE_LIMIT', 10),
        /** Appels API authentifiés par minute (par utilisateur ou IP). */
        'api_per_minute' => (int) env('API_RATE_LIMIT', 120),
    ],

    /** Scopes Sanctum délivrables via POST /api/v1/token. */
    'token_abilities' => ['lecture', 'suivi', 'tompro'],

    /** Types d'applications connectées (registre MOA). */
    'app_types' => [
        'interne' => 'Système interne CEEAC',
        'partenaire' => 'Partenaire technique / financier',
        'regional' => 'Plateforme régionale',
    ],

    /** Paramètres livraison webhooks (I-P2). */
    'webhooks' => [
        'max_tentatives' => (int) env('WEBHOOK_MAX_ATTEMPTS', 5),
        'timeout' => (int) env('WEBHOOK_TIMEOUT', 10),
        'backoff_seconds' => [60, 300, 900, 3600, 14400],
    ],

    /** Documentation OpenAPI (I-P3). */
    'openapi' => [
        'version' => '1.0.0',
        'fichier' => 'openapi/v1.json',
    ],

    /** Connecteur comptable TOMPRO (P2-8). */
    'tompro' => [
        'version' => '1.1',
        'default_limit' => (int) env('TOMPRO_EXPORT_LIMIT', 200),
        'max_limit' => 500,
    ],

];
