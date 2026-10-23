<?php

/**
 * Widgets affichés par persona sur le tableau de bord d'accueil (P1).
 * Clés réutilisables dans dashboard.blade.php et tableaux/index.
 */
return [

    'all_widgets' => [
        'budget_kpi', 'budget_chain', 'budget_reste', 'pap_summary', 'feux',
        'charts_nature', 'charts_engagements', 'charts_pilier', 'charts_structure',
        'activity_feed', 'admin_stats', 'recettes', 'tresorerie_link',
    ],

    'all_tableaux' => [
        'budget_kpi', 'budget_chain', 'budget_reste', 'pap_summary', 'recettes',
        'tresorerie_link', 'charts_all',
    ],

    'widgets' => [
        'executif' => [
            'budget_kpi', 'budget_chain', 'budget_reste', 'pap_summary',
            'feux', 'charts_nature', 'charts_engagements', 'charts_pilier', 'activity_feed',
        ],
        'pilotage' => [
            'budget_kpi', 'pap_summary', 'feux', 'charts_nature', 'charts_pilier', 'charts_structure',
        ],
        'operationnel' => [
            'pap_summary', 'feux', 'charts_pilier', 'activity_feed',
        ],
        'budgetaire' => [
            'budget_kpi', 'budget_chain', 'budget_reste', 'charts_nature', 'charts_engagements', 'recettes',
        ],
        'financier' => [
            'budget_kpi', 'budget_chain', 'budget_reste', 'charts_nature', 'charts_engagements', 'recettes', 'tresorerie_link',
        ],
        'audit' => [
            'budget_kpi', 'feux', 'admin_stats',
        ],
        'administration' => ['*'],
        'consultation' => [
            'budget_kpi', 'pap_summary',
        ],
    ],

    /** Sections activées pour le persona « tableaux décisionnels ». */
    'tableaux' => [
        'executif' => ['budget_kpi', 'budget_chain', 'recettes', 'charts_all'],
        'pilotage' => ['budget_kpi', 'pap_summary', 'charts_all'],
        'operationnel' => ['pap_summary', 'charts_structure'],
        'budgetaire' => ['budget_kpi', 'budget_reste', 'recettes', 'charts_all'],
        'financier' => ['budget_kpi', 'budget_reste', 'recettes', 'tresorerie_link', 'charts_all'],
        'audit' => ['budget_kpi', 'charts_all'],
        'administration' => ['*'],
        'consultation' => ['budget_kpi'],
    ],

];
