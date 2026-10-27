# 07 — Rapport d'intégrité des données

*Date : 25/10/2026. Base : PostgreSQL 18.4, `gest_budget_pap` (127.0.0.1:5433).*

## 1. Méthode

Script `database/scripts/empreintes_donnees_protegees.php` (lecture seule) : pour chaque catégorie protégée, lecture des colonnes significatives dans un ordre déterministe, normalisation des valeurs (nombres, booléens, valeurs nulles), puis empreinte SHA-256 du contenu et nombre de lignes. Les mots de passe chiffrés entrent dans le calcul de l'empreinte des utilisateurs (pour détecter toute modification) mais **ne figurent dans aucun fichier ni rapport** : seules les empreintes globales sont stockées.

| Fichier | Moment | Calculé le |
|---|---|---|
| `empreintes_avant.json` | Avant toute intervention | 25/10/2026 05:08 |
| `empreintes_apres_migration.json` | Après la migration additive et la commande de reprise | 25/10/2026 05:37 |
| `empreintes_apres.json` | Fin d'intervention, après la dernière correction | 25/10/2026 05:46 |

## 2. Résultat

| Catégorie | Table | Lignes avant | Lignes après | Empreinte |
|---|---|---|---|---|
| Utilisateurs (dont mots de passe, statut, structure) | `users` | 17 | 17 | identique |
| Rôles | `roles` | 14 | 14 | identique |
| Permissions | `permissions` | 392 | 392 | identique |
| Rôles des utilisateurs | `model_has_roles` | 17 | 17 | identique |
| Permissions des rôles | `role_has_permissions` | 1 044 | 1 044 | identique |
| Permissions directes | `model_has_permissions` | 0 | 0 | identique |
| Affectations aux structures | `user_structures` | 13 | 13 | identique |
| Organigramme | `structures` | 78 | 78 | identique |
| Nomenclature — titres | `budget_titres` | 11 | 11 | identique |
| Nomenclature — articles | `budget_articles` | 38 | 38 | identique |
| Nomenclature — paragraphes | `budget_paragraphes` | 119 | 119 | identique |
| Nomenclature — rubriques | `budget_rubriques` | 224 | 224 | identique |
| Budget 2026 (crédits ouverts et révisés) | `lignes_budgetaires` | 56 | 56 | identique |
| Exercices | `exercices` | 1 | 1 | identique |

**Aucun écart** sur les 14 catégories : utilisateurs, mots de passe, rôles, permissions, affectations, organigramme, nomenclature et Budget 2026 sont inchangés.

## 3. Écritures réellement effectuées sur la base

| Écriture | Portée |
|---|---|
| Migration `2026_10_25_100000_create_workflow_taches_tables` | Création de 2 tables nouvelles et d'une ligne dans `migrations` ; aucune table existante modifiée |
| `php artisan taches:synchroniser --historique` | 212 lignes dans `workflow_taches` (6 ouvertes, 206 reconstituées à partir des décisions passées) et leurs événements ; aucune écriture dans les tables métier |
| Alignement des libellés d'objet | 123 lignes de `workflow_taches.objet_libelle` (table nouvelle) |

Opérations **non exécutées** : aucun seeder, aucun `migrate:fresh`, `db:wipe` ou `migrate:refresh`, aucune purge, aucune modification de dossier financier (EB, ENG, LIQ, ORD, PAY), de document GED ou de journal d'audit.

## 4. Recette sans persistance

Les parcours de recette sur PostgreSQL (rapport 05) sont exécutés dans une transaction annulée : 212 tâches dont 6 ouvertes avant et après. Volumes métier constatés en fin d'intervention (non relevés au départ, donc donnés à titre indicatif) : 31 engagements, 25 liquidations, 21 ordonnancements, 22 paiements, 118 instances de workflow, 224 étapes d'instance. Seules les séquences PostgreSQL ont avancé (effet normal d'une transaction annulée, sans conséquence métier).

## 5. Point de restauration

- Code : `stash@{0}` « Point de restauration avant Mes Taches (25/10/2026) », créé sans toucher au répertoire de travail ; fichiers non suivis archivés avant intervention.
- Base : la migration est réversible (`down()` supprime uniquement les deux tables nouvelles) ; elle n'a pas été annulée.
