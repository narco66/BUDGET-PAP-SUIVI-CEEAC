<?php

return [
    /** Exiger au moins un justificatif (GED ou pièce) avant validation d'une collecte (phase S1). */
    'justificatif_obligatoire' => env('SUIVI_JUSTIFICATIF_OBLIGATOIRE', true),

    /** Synchroniser le taux physique depuis les tâches à la validation (phase S3). */
    'sync_taches_auto' => env('SUIVI_SYNC_TACHES_AUTO', true),
];
