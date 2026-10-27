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



    // Rappels et escalade des actions en attente (§ 17) — délais à valider par l'administration fonctionnelle.
    'rappels' => [
        'actif' => filter_var(env('NOTIFICATIONS_RAPPELS_ACTIFS', true), FILTER_VALIDATE_BOOL),
        'premier_heures' => (int) env('NOTIFICATIONS_RAPPEL_1_HEURES', 24),
        'second_heures' => (int) env('NOTIFICATIONS_RAPPEL_2_HEURES', 48),
        // Escalade au responsable hiérarchique après ce retard sur l'échéance de l'étape.
        'escalade_jours_retard' => (int) env('NOTIFICATIONS_ESCALADE_JOURS_RETARD', 3),
        // Délais comptés en jours ouvrables (samedi et dimanche exclus) dans le fuseau institutionnel.
        'jours_ouvrables' => filter_var(env('NOTIFICATIONS_JOURS_OUVRABLES', true), FILTER_VALIDATE_BOOL),
    ],

    // Notifications non désactivables par l'utilisateur (§ 19) : le canal in-app est toujours conservé.
    'obligatoires' => [
        'criticites' => ['critique', 'urgence'],
        'natures' => ['action'],
    ],

    // Déduplication (RN-10) : même clé pour un même destinataire dans cette fenêtre → pas de nouvel envoi.
    'deduplication_minutes' => (int) env('NOTIFICATIONS_DEDUPLICATION_MINUTES', 10),

    // Temps réel (§ 12) : diffusion sur le canal privé de l'utilisateur si un diffuseur réel est configuré ;
    // à défaut, l'interface interroge le serveur à cette cadence (reprise des notifications manquées).
    'temps_reel' => [
        'actif' => filter_var(env('NOTIFICATIONS_TEMPS_REEL', true), FILTER_VALIDATE_BOOL),
        'actualisation_secondes' => (int) env('NOTIFICATIONS_ACTUALISATION_SECONDES', 60),
    ],

    'purge_archives_jours' => (int) env('NOTIFICATIONS_PURGE_ARCHIVES_JOURS', 365),



];

