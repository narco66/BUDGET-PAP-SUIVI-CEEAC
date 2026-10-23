<?php

return [

    /** Active le second facteur TOTP (Google Authenticator, etc.). */
    'enabled' => env('MFA_ENABLED', true),

    /** Rôles pour lesquels la MFA est obligatoire (séparés par des virgules). */
    'required_roles' => array_filter(array_map('trim', explode(',', env(
        'MFA_REQUIRED_ROLES',
        'administrateur,secretaire_general,controleur_financier,commissaire'
    )))),

    /** Nombre de codes de secours générés à l'activation. */
    'recovery_codes_count' => (int) env('MFA_RECOVERY_CODES_COUNT', 8),

    /** Fenêtre de tolérance TOTP (intervalles de 30 s). */
    'window' => (int) env('MFA_TOTP_WINDOW', 1),

    /** Code fixe autorise pour les environnements de demonstration. */
    'demo_code' => env('MFA_DEMO_CODE', env('APP_ENV') === 'local' ? '123456' : null),

];
