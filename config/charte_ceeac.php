<?php

return [

    /**
     * Charte graphique institutionnelle CEEAC (P3-8).
     * Source unique pour exports PDF, Excel, Word, PowerPoint et UI web.
     */
    'institution' => env('CHARTE_INSTITUTION', 'COMMISSION DE LA CEEAC'),
    'sous_titre' => env('CHARTE_SOUS_TITRE', 'Communauté Économique des États de l\'Afrique Centrale'),
    'systeme' => env('CHARTE_SYSTEME', 'Système GEST-BUDGET-PAP'),

    'couleurs' => [
        'primaire' => env('CHARTE_COULEUR_PRIMAIRE', '#0a4a8f'),
        'fonce' => env('CHARTE_COULEUR_FONCE', '#073564'),
        'accent' => env('CHARTE_COULEUR_ACCENT', '#1a6fbf'),
        'fond_tableau' => env('CHARTE_COULEUR_FOND_TABLEAU', '#eef3f9'),
        'texte_secondaire' => env('CHARTE_COULEUR_TEXTE_SEC', '#666666'),
    ],

    'polices' => [
        'pdf' => env('CHARTE_POLICE_PDF', 'DejaVu Sans, sans-serif'),
        'web' => env('CHARTE_POLICE_WEB', 'system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif'),
    ],

    'pied_page' => [
        'mention' => 'Document généré par le système GEST-BUDGET-PAP — Commission de la CEEAC',
        'confidentialite' => 'Usage interne institutionnel — diffusion soumise à habilitation',
    ],

];
