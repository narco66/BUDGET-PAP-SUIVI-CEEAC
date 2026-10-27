# Audit de la migration MySQL → PostgreSQL 18

*Date : 2026-10-24. Source : MySQL 8.4 `gest_budget_pap` (Laragon). Cible : PostgreSQL 18.4, `127.0.0.1:5433`, base `gest_budget_pap`.*

## Synthèse

| Domaine | Verdict | Commentaire |
|---|---|---|
| Schéma (tables, colonnes, types, nullabilité) | ✅ Conforme | 210/210 tables, 2 597 colonnes comparées, 0 écart de type ou de nullabilité |
| Contraintes d'énumération | ✅ Conforme | 81 enums MySQL = 81 contraintes CHECK PostgreSQL, valeurs identiques |
| Clés étrangères | ✅ Conforme après correctif | 513/513 ; 2 règles ON DELETE divergentes corrigées (§ 3.2) |
| Index uniques | ✅ Conforme | Aucune unicité manquante |
| Index de performance sur FK | ⚠️ À traiter | 372 colonnes FK sans index en PostgreSQL (§ 3.3) |
| Données | ✅ Conforme après rechargement | Base PG recréée par un `migrate:fresh --seed` externe, puis rechargée depuis MySQL : 5 734 lignes, contenu identique (§ 2) |
| Séquences | ✅ Conforme | 198 séquences, aucune en retard sur `MAX(id)` |
| Migrations | ✅ Conforme | 163 migrations identiques + 1 corrective PG |
| Code applicatif (SQL brut) | ✅ Corrigé / ⚠️ 1 réserve | 13 requêtes non portables corrigées ; recherche `LIKE` sensible à la casse (§ 4.2) |
| Parcours applicatif | ✅ Conforme | 422 routes GET : 298 × 200, 0 erreur SQL (§ 5) |
| Sauvegarde | ✅ Conforme | `BackupService` utilise `pg_dump` PostgreSQL 18 (binaire présent) |

## 1. Méthode

1. Comparaison automatisée des catalogues `information_schema` / `pg_catalog` des deux bases : tables, colonnes (famille de type, précision décimale, nullabilité), index, clés étrangères avec règle ON DELETE, migrations.
2. Comparaison des données table par table : nombre de lignes **et** empreinte SHA-256 du contenu normalisé (booléens, décimaux, dates, JSON), lignes triées par `id`.
3. Contrôle des séquences (`nextval` > `MAX(id)`) et de l'intégrité référentielle (recherche d'orphelins sur les 513 FK).
4. Revue du SQL brut dans `app/` (`selectRaw`, `whereRaw`, `DB::raw`, `having`...).
5. Parcours de toutes les routes GET de l'application, connecté en administrateur, dans une transaction annulée.
6. Connexion HTTP réelle sur `http://budget-pap-suivi-ceeac.test`.

## 2. Données — constat bloquant

Juste après la copie, le contrôle donnait **209 tables, 5 734 lignes, 0 écart**, 0 orphelin, et la connexion `directeur@ceeac.test` fonctionnait.

L'audit de parité relancé ensuite trouve 65 tables divergentes (ex. `activites` : 236 en MySQL, 21 en PG ; `users` : 15 contre 2). Analyse :

- Toutes les lignes PG ont été créées le 2026-10-24 à 20:16 UTC (21:16 heure locale), migrations en un seul lot : signature d'un `php artisan migrate:fresh --seed`.
- Les tests PHPUnit sont hors de cause : sonde vérifiée, ils utilisent SQLite `:memory:`.
- Une autre session de travail a préparé la migration PostgreSQL en parallèle (modifications de `.env`, `.env.example`, `BackupService`, migrations, script `database/scripts/copier_mysql_vers_pgsql.php` entre 20:55 et 20:56).
- **La base MySQL source est intacte** (236 activités, 15 utilisateurs) : aucune perte définitive.

**Résolution (2026-10-24, 21 h 38) :** schéma reconstruit par les migrations, puis données rechargées depuis MySQL (209 tables, 5 734 lignes, 198 séquences recalées). La comparaison du contenu ligne à ligne donne 204 tables identiques. Les seuls écarts proviennent des connexions réelles du jeu d'essai (sessions, `login_logs`, `journaux_audit`, `users.derniere_connexion_at` du compte de test) et du cache. Jeu d'essai rejoué : 44 cas conformes, 0 non conforme, 2 réserves. Voir `docs/jeu-essai/`.

## 3. Schéma

### 3.1 Écarts corrigés dans les migrations

| Migration | Problème sous PostgreSQL | Correctif |
|---|---|---|
| `2026_06_11_000003_assouplir_nomenclature_budgetaire` | `enum → string` par `change()` conserve la contrainte CHECK : sens `financement` / `hors_bilan` refusés | Suppression de `budget_titres_sens_check` |
| `2026_06_21_000001_liaison_pap_piliers_revision` | Ajout du statut `en_revision` réservé à MySQL | Réécriture de `paps_statut_check` |
| `2026_06_23_000001_rbm_integrite_activites_taches` | `having('total')` : alias interdit dans HAVING | `havingRaw('COUNT(*) > 1')` |
| `2026_07_19_000001_suivi_physique_s4` | Type `mensuel` réservé à MySQL | Réécriture de `campagnes_suivi_type_check` |
| `2026_07_26_000001_ajouter_type_impact_evaluations` | `change()` sur un enum : SQL invalide | Réécriture de `evaluations_periodiques_type_check` |

### 3.2 Clés étrangères `indicateurs`

`2026_06_23_000002` ne recréait les FK `type_indicateur_id` / `unite_mesure_id` en `RESTRICT` que sous MySQL. En PostgreSQL elles restaient en `SET NULL` sur des colonnes `NOT NULL`. Corrigé par la nouvelle migration `2026_10_24_230000_pgsql_indicateurs_fk_restrict` (sans effet hors PostgreSQL), appliquée et vérifiée.

### 3.3 Index sur clés étrangères (recommandation)

MySQL (InnoDB) crée automatiquement un index pour chaque clé étrangère. **PostgreSQL ne le fait pas.** 372 colonnes FK n'ont pas d'index (ex. `activites.produit_id`, `budget_preparations.structure_id`, `appels_fonds.etat_membre_id`).

- Impact actuel faible (base de 30 Mo).
- Impact futur : jointures et filtres plus lents, et chaque `DELETE` sur une table parente parcourt intégralement les tables filles.
- Recommandation : une migration PostgreSQL qui crée les index manquants, générée à partir du catalogue.

### 3.4 Types natifs

Trois colonnes passent de `char(36)` à `uuid` natif : `ged_documents.uuid`, `notifications.id`, `notification_logs.laravel_notification_id`. Une valeur mal formée provoque une erreur SQL au lieu d'un « non trouvé ». Les routes concernées sont désormais contraintes par `whereUuid()` (`verifier/ged/{uuid}`, `notifications/{id}/...` web et API).

## 4. Code applicatif

### 4.1 Corrigé

- Littéraux SQL entre guillemets doubles (lus comme des noms de colonnes par PostgreSQL) : 10 requêtes dans `TresorerieService`, `TableauBordService`, `CampagneSuiviController`, `NomenclatureStatsService`, `LigneCreditsService`, `DepenseExportController`, `ReportingService`, `CampagneSuiviApiController`, `InstitutionMetricsService`.
- `AuditCadreOrganiqueCommand` : alias dans HAVING, et `COALESCE(booléen, 0)` (mélange booléen/entier refusé).

### 4.2 Réserve : recherche sensible à la casse

Avec la collation MySQL `utf8mb4_unicode_ci`, `LIKE` ignorait la casse. Sous PostgreSQL, `LIKE` la respecte : une recherche « budget » ne trouve plus « Budget ». 65 occurrences dans 39 fichiers (filtres de liste, recherche GED, API).

Recommandation : passer ces filtres à `whereLike(..., caseSensitive: false)`, qui reste portable MySQL/SQLite/PG, ou à `ILIKE` derrière un test du pilote.

## 5. Parcours applicatif

422 routes GET parcourues en administrateur (MFA active) sur PostgreSQL :

- 298 réponses 200, 9 redirections, 112 réponses 404 (paramètre de test `1` inexistant) ;
- 0 erreur SQL après correctifs ;
- 2 erreurs `TypeError` sur `rbm/export/{niveau}/{exercice}` et `rbm/pdf/catalogue/...`, dues au jeu de paramètres du parcours et indépendantes du SGBD.

Connexion HTTP réelle : `POST /login` → 302 `/dashboard` → 200.

## 6. Configuration

| Élément | Valeur | Remarque |
|---|---|---|
| Serveur | PostgreSQL 18.4, port 5433 | PostgreSQL 17 tourne aussi sur 5432 : ne pas confondre |
| Encodage / collation | UTF8 / `French_France.1252` | Tri français correct |
| Fuseau serveur | `Africa/Lagos` (WAT) | Identique à MySQL (fuseau système) ; l'application écrit en UTC |
| Sessions, cache, files d'attente | pilote `database` | Désormais sur PostgreSQL |
| `max_connections` / `shared_buffers` | 100 / 128 Mo | Valeurs par défaut, suffisantes en local |
| Compte | `postgres` / mot de passe simple | Acceptable en local uniquement ; compte applicatif dédié requis hors poste de développement |

## 7. Plan d'action

| # | Action | Priorité |
|---|---|---|
| 1 | ~~Recharger les données PG depuis MySQL, puis rejouer le contrôle de parité~~ | Fait le 2026-10-24 |
| 2 | Rendre les recherches insensibles à la casse (65 occurrences) | Haute |
| 3 | Créer les index des 372 colonnes FK sous PostgreSQL | Moyenne |
| 4 | Compte PostgreSQL applicatif dédié, hors super-utilisateur, avant tout déploiement | Moyenne (hors local) |
| 5 | Ajouter un job CI qui exécute les tests sur PostgreSQL (les tests actuels tournent sous SQLite et n'auraient détecté aucun des écarts ci-dessus) | Moyenne |
