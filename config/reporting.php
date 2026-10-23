<?php

return [
    /** Indexation automatique GED après génération d'un rapport. */
    'ged_auto_index' => env('REPORTING_GED_AUTO_INDEX', true),

    /** Planification automatique des rapports périodiques (scheduler Laravel). */
    'schedule' => [
        'enabled' => env('REPORTING_SCHEDULE_ENABLED', true),
        'trimestriel' => env('REPORTING_SCHEDULE_TRIMESTRIEL', true),
        'semestriel' => env('REPORTING_SCHEDULE_SEMESTRIEL', true),
        'annuel' => env('REPORTING_SCHEDULE_ANNUEL', true),
    ],

    /** Génération asynchrone (file d'attente) pour les rendus lourds. */
    'async' => [
        'enabled' => env('REPORTING_ASYNC_ENABLED', true),
        /** Types toujours déportés en arrière-plan. */
        'types' => [
            'trimestriel', 'semestriel', 'annuel', 'ipsas_etat_financier',
            'rbm_gar', 'execution_axe', 'bailleur', 'categorie',
            'ecarts_physique_financier', 'recettes_financier', 'performance_departement',
        ],
        /** Formats déportés pour tout type (rendu coûteux). */
        'formats' => ['word', 'pptx'],
        'queue' => env('QUEUE_REPORTING', 'reporting'),
    ],
];
