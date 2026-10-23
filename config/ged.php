<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module GED — configuration
    |--------------------------------------------------------------------------
    */

    'taille_max_mo' => (int) env('GED_TAILLE_MAX_MO', 10),

    'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'],

    'antivirus' => [
        'active' => (bool) env('GED_ANTIVIRUS_ACTIF', false),
        'driver' => env('GED_ANTIVIRUS_DRIVER', 'null'), // null | clamav | test
        'clamscan_path' => env('GED_CLAMSCAN_PATH', 'clamscan'),
        'quarantine' => (bool) env('GED_ANTIVIRUS_QUARANTINE', true),
    ],

    'ocr' => [
        'active' => (bool) env('GED_OCR_ACTIF', false),
        'driver' => env('GED_OCR_DRIVER', 'basique'), // basique | tesseract
        'tesseract_path' => env('GED_TESSERACT_PATH', 'tesseract'),
    ],

    'signature' => [
        'provider' => env('GED_SIGNATURE_PROVIDER', 'interne'), // interne | externe | test
        'externe' => [
            'url' => env('GED_SIGNATURE_API_URL'),
            'api_key' => env('GED_SIGNATURE_API_KEY'),
            'niveau' => env('GED_SIGNATURE_NIVEAU', 'qualifiee'), // avancee | qualifiee
        ],
    ],

    'notifications' => [
        'mail' => (bool) env('GED_NOTIFICATIONS_MAIL', false),
        'expiration_jours_avant' => (int) env('GED_EXPIRATION_ALERTE_JOURS', 30),
    ],
];
