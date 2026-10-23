<?php

return [

    /** Purge planifiée des journaux d'audit (P2-4). */
    'purge' => [
        'enabled' => env('AUDIT_PURGE_ENABLED', true),
        /** Délai minimal autorisé (évite une purge accidentelle trop agressive). */
        'min_jours' => (int) env('AUDIT_PURGE_MIN_JOURS', 90),
        'batch_size' => (int) env('AUDIT_PURGE_BATCH_SIZE', 5000),
    ],

    /**
     * Rétention par table (jours). Les enregistrements plus anciens sont éligibles à la purge.
     * Colonne temporelle : created_at.
     */
    'retention' => [
        'journaux_audit' => (int) env('AUDIT_RETENTION_JOURNAUX_JOURS', 2555),
        'audit_workflow' => (int) env('AUDIT_RETENTION_WORKFLOW_JOURS', 2555),
        'login_logs' => (int) env('AUDIT_RETENTION_LOGIN_JOURS', 365),
        'api_audit_logs' => (int) env('AUDIT_RETENTION_API_JOURS', 365),
        'audit_depenses' => (int) env('AUDIT_RETENTION_DEPENSES_JOURS', 1825),
        'audit_reporting' => (int) env('AUDIT_RETENTION_REPORTING_JOURS', 1825),
        'audit_notifications' => (int) env('AUDIT_RETENTION_NOTIFICATIONS_JOURS', 365),
        'audit_suivi_physique' => (int) env('AUDIT_RETENTION_SUIVI_PHYSIQUE_JOURS', 1825),
        'ged_journaux' => (int) env('AUDIT_RETENTION_GED_JOURS', 2555),
        'audit_integrite_data' => (int) env('AUDIT_RETENTION_INTEGRITE_JOURS', 730),
    ],

    /** Contrôle d'intégrité global planifié (P2-10). */
    'integrity' => [
        'enabled' => env('DATA_INTEGRITY_ENABLED', true),
        'modules' => [
            'transversal', 'organisation', 'pap', 'budget', 'rbm', 'depense', 'suivi', 'compteurs',
        ],
        'seuil_echec' => env('DATA_INTEGRITY_SEUIL', 'warning'),
    ],

];
