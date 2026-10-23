<?php

return [
    /** Séparation des fonctions (SoD) — étapes consécutives, initiateur ≠ validateur, rôles incompatibles. */
    'sod' => [
        'enabled' => env('WORKFLOW_SOD_ENABLED', true),
        'creator_cannot_validate' => env('WORKFLOW_SOD_CREATOR_CANNOT_VALIDATE', true),
        'incompatible_roles_enabled' => env('WORKFLOW_SOD_INCOMPATIBLE_ROLES_ENABLED', true),
        /** Paires de rôles métier qui ne doivent pas être cumulés sur un même compte. */
        'incompatible_roles' => [
            ['gestionnaire_budgetaire', 'controleur_financier'],
            ['gestionnaire_budgetaire', 'comptable'],
            ['controleur_financier', 'comptable'],
        ],
    ],

    /** Relances SLA sur étapes en retard (P1). */
    'relance' => [
        'delai_defaut_jours' => (int) env('WORKFLOW_DELAI_DEFAUT_JOURS', 5),
        'intervalle_jours' => (int) env('WORKFLOW_RELANCE_INTERVALLE_JOURS', 3),
    ],
];
