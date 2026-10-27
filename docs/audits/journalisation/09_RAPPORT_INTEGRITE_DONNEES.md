# 09 — Rapport d'intégrité des données

*Date : 25/10/2026. Base : PostgreSQL 18.4, `gest_budget_pap`.*

## 1. Méthode

- `database/scripts/empreintes_donnees_protegees.php` (lecture seule) : 14 catégories protégées, colonnes significatives lues dans un ordre déterministe, valeurs normalisées, empreinte SHA-256 et nombre de lignes. Les mots de passe chiffrés participent au calcul de l'empreinte des utilisateurs mais **ne figurent dans aucun fichier ni rapport**.
- `database/scripts/empreinte_journal_central.php` (lecture seule, ajouté pour cette intervention) : empreinte du contenu historique de `journaux_audit` (colonnes d'origine), pour prouver qu'aucun événement existant n'a été modifié ni supprimé.

| Fichier | Moment |
|---|---|
| `empreintes_avant.json`, `empreinte_journal_avant.txt` | Avant les migrations du module (25/10/2026) |
| `empreintes_apres.json`, `empreinte_journal_apres.txt` | Fin d'intervention |

## 2. Données institutionnelles protégées

| Catégorie | Lignes avant | Lignes après | Empreinte |
|---|---|---|---|
| Utilisateurs (identifiants, mots de passe chiffrés, statut, structure) | 17 | 17 | identique |
| Rôles | 14 | 14 | identique |
| Permissions | 392 | 392 | identique |
| Rôles des utilisateurs | 17 | 17 | identique |
| Permissions des rôles | 1 044 | 1 044 | identique |
| Permissions directes | 0 | 0 | identique |
| Affectations aux structures | 13 | 13 | identique |
| Organigramme | 78 | 78 | identique |
| Nomenclature (titres, articles, paragraphes, rubriques) | 11 / 38 / 119 / 224 | identiques | identiques |
| Budget 2026 (crédits ouverts et révisés) | 56 | 56 | identique |
| Exercices | 1 | 1 | identique |

**Aucun écart** sur les 14 catégories.

## 3. Historique du journal central

| Mesure | Avant | Après |
|---|---|---|
| Événements existants (id ≤ 7032) | 2 871 | 2 871 |
| Empreinte du contenu historique | `6d308002…1d84844e` | `6d308002…1d84844e` (identique) |

Les événements existants n'ont été ni modifiés, ni supprimés, ni réécrits : les colonnes ajoutées sont nullables. Seule `recorded_at`, ajoutée avec une valeur par défaut, a reçu pour ces lignes l'heure de la migration ; elle est exclue de l'empreinte et masquée à l'affichage. Les 20 événements apparus depuis (2 891 au total) sont des événements du module lui-même (scellement, contrôle), des connexions réelles d'utilisateurs et des connexions de contrôle visuel.

## 4. Dossiers financiers et historiques

Volumes en fin d'intervention : 35 expressions de besoin, 31 engagements, 25 liquidations, 21 ordonnancements, 22 paiements, 118 instances de workflow, 325 lignes `audit_workflow`, 212 tâches. Aucun dossier ni historique n'a été modifié : la recette PostgreSQL (rapport 07) s'exécute dans une transaction annulée, et le nombre d'événements du journal est identique avant et après chaque recette.

## 5. Écritures effectuées

| Écriture | Tables |
|---|---|
| Migrations additives | `journaux_audit` (colonnes), `audit_scellements`, `audit_controles_integrite`, `audit_exports`, `audit_archives` (créées), fonction et déclencheurs PostgreSQL |
| Scellement initial et contrôle d'intégrité | `audit_scellements` (1), `audit_controles_integrite` (1), `journaux_audit` (événements du dispositif), `alertes` (1 alerte HORLOGE, faux positif documenté au rapport 06) |
| Fichier hors base | `storage/app/private/audit/scellements/SCEL-20261025-061758-F86433.json` |

Opérations **non exécutées** : aucun seeder, aucune purge, aucune modification de rôle, de permission, d'affectation, de mot de passe, de dossier financier, de document GED ni de journal existant.

## 6. Point de restauration

- Code : `stash@{0}` (point de restauration Git) ; les fichiers du module sont nouveaux ou modifiés de façon ciblée (rapport 04).
- Base : les deux migrations sont réversibles (`php artisan migrate:rollback --step=2` supprimerait les tables et colonnes ajoutées) ; **ne pas l'exécuter sans décision**, car cela effacerait les scellements et les colonnes renseignées depuis.
