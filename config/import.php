<?php

/*
 * Module Import : intégration contrôlée de données externes (lots traçables, correspondance des colonnes,
 * contrôles, simulation, approbation, exécution transactionnelle). Voir docs/module-import.md.
 */
return [
    /** Stockage privé des fichiers sources (disque non public). */
    'disque' => env('IMPORT_DISQUE', 'local'),
    'repertoire' => 'imports',

    /** Taille maximale d'un fichier (Mo) et nombre maximal de lignes de données par lot. */
    'taille_max_mo' => (int) env('IMPORT_TAILLE_MAX_MO', 20),
    'lignes_max' => (int) env('IMPORT_LIGNES_MAX', 20000),

    /** Formats acceptés : extension → types MIME réels admis (contrôle du contenu, pas seulement du nom). */
    'formats' => [
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
        'json' => ['application/json', 'text/plain', 'text/json'],
    ],

    /**
     * Exercices dont les lignes budgétaires sont protégées : aucune écriture par import, quel que soit le
     * statut du budget (les modifications passent par les procédures budgétaires). Liste d'années, séparées
     * par des virgules ; vide pour lever la protection.
     */
    'exercices_proteges' => array_values(array_filter(array_map('intval', explode(',', (string) env('IMPORT_EXERCICES_PROTEGES', '2026'))))),

    /** Au-delà de ce nombre de lignes à écrire, l'exécution passe par un job en file (suivi d'avancement). */
    'seuil_asynchrone' => (int) env('IMPORT_SEUIL_ASYNCHRONE', 2000),

    /** Nombre de lignes affichées par page dans la prévisualisation et le rapport d'anomalies. */
    'par_page' => 50,

    /** Rétention des fichiers sources des lots terminés, annulés ou rejetés (jours) ; 0 = conservation. */
    'retention_fichiers_jours' => (int) env('IMPORT_RETENTION_FICHIERS_JOURS', 0),
];
