<?php



return [



    'email' => [

        'enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', true),

        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@ceeac.test'),

        'from_name' => env('MAIL_FROM_NAME', 'CEEAC — GEST-BUDGET'),

        'queue' => env('NOTIFICATIONS_EMAIL_QUEUE', true),

        'max_tentatives' => (int) env('NOTIFICATIONS_EMAIL_MAX_TRIES', 3),

    ],



    'sms' => [

        'enabled' => env('NOTIFICATIONS_SMS_ENABLED', true),

        'provider' => env('NOTIFICATIONS_SMS_PROVIDER', 'log'),

        'max_tentatives' => (int) env('NOTIFICATIONS_SMS_MAX_TRIES', 3),

        'twilio' => [

            'sid' => env('TWILIO_SID'),

            'token' => env('TWILIO_TOKEN'),

            'from' => env('TWILIO_FROM'),

        ],

    ],



    'escalade' => [

        'criticites' => ['critique', 'urgence'],

        'delai_niveau_heures' => (int) env('NOTIFICATIONS_ESCALADE_DELAI_HEURES', 24),

        'niveaux' => [

            1 => [

                'roles' => ['directeur', 'chef_service'],

                'canaux' => ['database', 'email'],

            ],

            2 => [

                'roles' => ['commissaire', 'controleur_financier'],

                'canaux' => ['database', 'email', 'sms'],

            ],

            3 => [

                'roles' => ['secretaire_general', 'president'],

                'canaux' => ['database', 'email', 'sms'],

            ],

        ],

    ],



    'purge_archives_jours' => (int) env('NOTIFICATIONS_PURGE_ARCHIVES_JOURS', 365),



];

