<?php

return [

    /**
     * Profil production CEEAC (P2-2).
     * Dev/local : database pour queue & cache.
     * Prod : redis recommandé (voir .env.example).
     */
    'production' => [
        'queue_connection' => env('PERFORMANCE_PROD_QUEUE', 'redis'),
        'cache_store' => env('PERFORMANCE_PROD_CACHE', 'redis'),
    ],

    /** Files d'attente nommées — worker : php artisan queue:work redis --queue=reporting,notifications,default */
    'queues' => [
        'default' => env('QUEUE_DEFAULT', 'default'),
        'reporting' => env('QUEUE_REPORTING', 'reporting'),
        'notifications' => env('QUEUE_NOTIFICATIONS', 'notifications'),
        'webhooks' => env('QUEUE_WEBHOOKS', 'webhooks'),
    ],

    'redis' => [
        'health_check_enabled' => env('REDIS_HEALTH_CHECK', true),
        /** Alerte cockpit si prod utilise encore database pour queue/cache. */
        'warn_database_in_production' => env('REDIS_WARN_PRODUCTION', true),
    ],

    /** Compression gzip des réponses HTML/JSON par l'application (middleware CompresserReponse). */
    'compression' => [
        'enabled' => env('APP_COMPRESSION', true),
        'niveau' => (int) env('APP_COMPRESSION_NIVEAU', 5),
    ],

];
