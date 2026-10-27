<?php

/**
 * Paramètres de la chaîne de dépense (procédures 24/10/2026).
 * Les mappings de rôles sont des hypothèses configurables, pas des décisions institutionnelles.
 */
return [
    'role_drhmg' => env('CHAINE_DEPENSE_ROLE_DRHMG', 'directeur'),
    'role_directeur_budget' => env('CHAINE_DEPENSE_ROLE_DIR_BUDGET', 'directeur'),
    'role_agent_comptable' => env('CHAINE_DEPENSE_ROLE_AGENT_COMPTABLE', 'agent_comptable'),

    /** Codes / sigles organiques acceptés pour le visa ENG « Direction du Budget ». Vide = rôle seul. */
    'codes_structure_directeur_budget' => array_values(array_filter(explode(',', (string) env(
        'CHAINE_DEPENSE_CODES_DIR_BUDGET',
        'DIR-BUDGET'
    )))),

    /** Types organiques traités comme directions d’appui (circuit PAP → SG). */
    'types_structure_appui' => array_values(array_filter(explode(',', (string) env(
        'CHAINE_DEPENSE_TYPES_APPUI',
        'secretariat,presidence,direction_appui,appui'
    )))),

    'codes_structure_appui' => array_values(array_filter(explode(',', (string) env(
        'CHAINE_DEPENSE_CODES_APPUI',
        'SG,PRES,DRHMG'
    )))),

    'ordonnancement' => [
        'seuil_xaf' => env('CHAINE_DEPENSE_SEUIL_ORD_XAF', '5000000.00'),
        'assiette' => env('CHAINE_DEPENSE_SEUIL_ASSIETTE', 'net'),
        'juridiquement_valide' => filter_var(env('CHAINE_DEPENSE_SEUIL_JURIDIQUE', false), FILTER_VALIDATE_BOOL),
    ],

    /**
     * Enchaînement automatique (procédures ENG § 3.1-4 et LIQ § 3.1) :
     * - EB approuvée → engagement généré, crédits réservés et soumis à la Direction du Budget ;
     * - engagement visé → liquidation générée et circuit lancé (certification du service fait par le service initiateur).
     */
    'automatisation' => [
        'soumission_engagement' => filter_var(env('CHAINE_DEPENSE_SOUMISSION_AUTO_ENG', true), FILTER_VALIDATE_BOOL),
        'demarrage_liquidation' => filter_var(env('CHAINE_DEPENSE_DEMARRAGE_AUTO_LIQ', true), FILTER_VALIDATE_BOOL),
    ],
];
