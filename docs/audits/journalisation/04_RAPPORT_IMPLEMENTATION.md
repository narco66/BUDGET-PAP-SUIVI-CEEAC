# 04 — Rapport d'implémentation : Journalisation, Traçabilité et Horodatage

*Date : 25/10/2026. Lots J1 à J8 du plan 03. Modifications additives ou ciblées ; aucune commande destructive (`migrate:fresh`, `db:wipe`, `migrate:refresh`, `git reset --hard`, `git clean`) ; aucun seeder exécuté ; aucune ligne de journal existante modifiée ou supprimée.*

## 1. Principe

Le journal existant `journaux_audit` devient le journal central du référentiel (équivalent `audit_events`), enrichi sans réécriture de l'historique. `AuditLogger` reste le point d'entrée unique, avec la même signature : les 77 modèles audités et les ~98 appels existants bénéficient automatiquement de la classification, de la corrélation, de l'horodatage en microsecondes et de l'empreinte. Aucun journal concurrent n'est créé : les journaux spécialisés existants sont conservés, protégés et agrégés en lecture.

## 2. Fonctionnalités créées

| Fonctionnalité | Composants |
|---|---|
| Classification et corrélation des événements (catégorie, module, code d'acte, résultat, gravité, unité, titulaire représenté, instance de workflow, tâche, dossier racine EB → PAY, identifiant de requête) | `app/Support/AuditLogger.php` (enrichi), `app/Support/AuditContexte.php`, middleware `IdentifiantRequete` (en-tête `X-Request-Id`) |
| Horodatage UTC à la microseconde, instants distincts, affichage à Libreville | Colonnes `TIMESTAMPTZ(6)`, `app/Support/Horodatage.php`, `config/app.php` (`timezone_affichage`), `config/database.php` (session PostgreSQL en UTC) |
| Contrôle de l'horloge (dérive base/application, état NTP) | `app/Services/Audit/HorlogeService.php` |
| Empreinte canonique SHA-256 | `app/Support/AuditEmpreinte.php` |
| Scellement chaîné signé, dépôt hors base, vérification, rapport, alertes | `app/Services/Audit/AuditIntegriteService.php`, modèles `AuditScellement`, `AuditControleIntegrite` |
| Archivage contrôlé et vérifiable | `app/Services/Audit/AuditArchivageService.php`, modèle `AuditArchive` |
| Commande d'exploitation | `php artisan audit:journal sceller | verifier | horloge | archiver --avant=AAAA-MM-JJ` ; planification horaire (scellement) et quotidienne (vérification) |
| Journal de sécurité : accès refusés, échecs de connexion | `app/Support/JournalSecurite.php`, gestionnaire d'exceptions (`bootstrap/app.php`), `LoginController`, `SessionAuthController` |
| Cloisonnement de la consultation | `app/Services/Audit/AuditAccesService.php` (propres événements / unité dont l'utilisateur est responsable dans l'organigramme / étendu / sécurité) |
| Permissions du § 8 sans modification des rôles | `app/Support/AuditPermissionAliases.php` (enregistré dans `Gate::before`) |
| Recherche, statistiques, chronologie consolidée d'un dossier | `app/Services/Audit/AuditRechercheService.php` |
| Cycle de traitement des alertes de journalisation | `app/Services/Audit/AuditAlerteService.php` (sur le registre `alertes` existant) |
| Écrans | Tableau de bord, journal des opérations (11 filtres, exports), fiche d'événement (temps, acteur, métier, avant/après, intégrité, corrélation), journal de sécurité, historique de dossier, historique d'utilisateur, intégrité (scellements, contrôles, exécution) — `resources/views/audit/*` ; groupe de menu dédié « Journalisation et traçabilité » (section Gouvernance SI : tableau de bord, journal des opérations, journal de sécurité, journal des connexions, intégrité), visible selon les permissions |
| Exports CSV et JSON tracés (référence, acteur, périmètre, filtres, empreinte SHA-256) | `AuditController::export`, modèle `AuditExport` |
| API REST en lecture | `app/Http/Controllers/Api/V1/AuditApiController.php`, 12 routes `api/v1/audit/*` |

## 3. Fonctionnalités corrigées

| Anomalie (rapport 01) | Correction |
|---|---|
| S1 — journal lisible par 12 rôles | Consultation cloisonnée côté serveur ; l'accès étendu exige `audit.consulter_journal` (auditeur, administrateur) |
| S2 — journal des connexions lisible avec `audit.voir` | Exige `utilisateur.consulter_journal` ou `audit.security.view` |
| S3 — journaux modifiables | Gardes applicatives + déclencheurs PostgreSQL append-only sur 8 journaux |
| S4 — purge mensuelle silencieuse | Désactivée par défaut ; journal central purgeable uniquement sur plages archivées et vérifiées ; purge journalisée et autorisée dans sa seule transaction |
| S5 — aucune empreinte | Empreintes, chaînage, scellement, vérification |
| S6 — masquage limité | Liste noire étendue, appliquée à toute profondeur |
| S8 — 403 non journalisés | Événement `acces_refuse` limité à un par acteur, chemin et minute |
| Fuseau de session PostgreSQL divergent | Session en UTC |
| Heures UTC affichées comme heures locales | Affichage `Africa/Libreville` avec l'instant UTC en info-bulle |
| Rôles, profils et structures des utilisateurs non tracés | Événements avant/après dans `UserService` |
| Délégations, intérims, seuil d'ordonnancement, alertes non audités | Trait `Auditable` ajouté aux modèles `Delegation`, `Interim`, `ChaineDepenseParametre`, `Alerte` |
| Décisions de workflow peu structurées ; double inscription du démarrage | Un événement central par décision, avec acte (VISA, CERTIFICATION, SIGNATURE, APPROBATION, VALIDATION, REJET, RETOUR), étape, rôle compétent, tâche, titulaire, seuil ORD et version, reliquat PAY ; double inscription supprimée |

## 4. Fonctions conservées

`journaux_audit` (2 871 lignes intactes, empreinte du contenu historique identique avant/après), `AuditLogger::enregistrer()` (signature inchangée), trait `Auditable`, `login_logs`, `audit_workflow`, `workflow_tache_evenements`, `visas_electroniques`, `ged_journaux`, `audit_notifications`, `audit_reporting`, écran `/audit` (enrichi), `/logs/connexions`, `/workflow/audit`, commande `audit:purge-journaux` (durcie), `data:integrity-check`, registre des alertes et son écran.

## 5. Migrations (appliquées sur PostgreSQL le 25/10/2026)

| Migration | Contenu |
|---|---|
| `2026_01_02_000003_journalisation_colonnes_journaux_audit` | 19 colonnes nullables et 6 index sur `journaux_audit`. Placée juste après la création de la table, car des migrations ultérieures créent déjà des modèles audités (même contrainte que la colonne `role`) |
| `2026_10_25_200000_journalisation_tracabilite_horodatage` | Tables `audit_scellements`, `audit_controles_integrite`, `audit_exports`, `audit_archives` ; fonction et déclencheurs `journal_append_only` (PostgreSQL) |

Les deux migrations sont réversibles (`down()`) et n'ont pas été annulées.

## 6. Écritures effectuées sur la base réelle

| Écriture | Portée |
|---|---|
| Migrations ci-dessus | Ajouts de colonnes et de tables |
| Scellement initial `SCEL-20261025-061758-F86433` | 1 ligne dans `audit_scellements`, 1 fichier de dépôt, 1 événement `audit_scellement` |
| Contrôle `CTRL-20261025-061759-2BA9F2` | 1 rapport, 1 événement, et (faux positif d'analyse NTP, voir rapport 06) 1 alerte `HORLOGE` et 1 événement `horloge_desynchronisee` |
| Connexions de contrôle visuel (compte auditeur de démonstration) | Événements de connexion normaux |

Aucune écriture dans les tables métier, institutionnelles ou de référence. Les parcours de recette sont exécutés dans une transaction annulée.

## 7. Limites connues

Voir la matrice 02 : interfaces en Blade (pas de React/Tailwind/Font Awesome 7), dépôt de scellement local, compte PostgreSQL superutilisateur, durées de conservation non validées, journaux spécialisés non chaînés, événements antérieurs au module non classés à l'écriture (classement déduit à l'affichage) et non recherchables par référence, administrateur doté de tous les droits par `Gate::before`, absence de tests Playwright et de mesures de charge.
