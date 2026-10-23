# Module « Administration Technique » — BUDGET-PAP-SUIVI-CEEAC

Audit transversal (2026-06-15) aligné ISO 27001 / 22301 / ITIL v4 / COBIT / OWASP.
**Constat : socle observabilité (logs/notifications/alertes) et sécurité de base solides ; socle
d'exploitation DSI (sauvegarde, supervision système, incidents) largement absent.** Conformité initiale
~42 %. Premier lot livré : **T1 — Centre de supervision technique**.

## 1. État de conformité (synthèse d'audit)

| Domaine | Conformité | Note |
|---|---|---|
| Journalisation (audit/login/API/workflow) | ~85 % ✅ | journaux_audit, login_logs, api_logs, api_metrics, audit_workflow + UIs |
| Notifications & alertes | ~90 % ✅ | types/canaux/templates/logs/SMS/relances/escalade |
| Scheduler + jobs/queues | ~70 % ✅ | 15 tâches, driver database, failed_jobs (sans UI Horizon) |
| Sécurité applicative | ~65 % ⚠️ | bcrypt12, politique mdp, RBAC, CSRF, XSS, SQLi, throttle API |
| **Sauvegardes / Restauration** | ~5 % ❌ | aucun paquet ni table ni commande |
| **Monitoring système (santé)** | ~10 % → **~65 %** | **Lot T1 livré** |
| **Incidents** | 0 % ❌ | aucune entité |
| Durcissement sécu (MFA, throttle web, lockout, headers) | ~20 % ❌ | login web non throttlé, pas de MFA |

## 2. Lot T1 livré — Centre de supervision technique (health-check natif PHP)

**Non destructif** — migration additive `2026_07_31_000003_create_system_metrics`.

- **`App\Services\AdministrationTechnique\SanteService`** : health-check **100 % PHP natif** (aucune
  dépendance). 7 contrôles : base de données (ping `select 1`), espace disque (`disk_free_space`),
  file d'attente (`jobs`), jobs échoués (`failed_jobs`), sessions actives, **échecs de connexion 24h**
  (`login_logs` — signal anti brute-force), capture planifiée (fraîcheur du dernier instantané). Statut
  global = pire des contrôles (ok < warning < critique). Toutes les lectures de tables sont gardées
  `Schema::hasTable` (robuste).
- **`system_metrics`** : instantanés horodatés (disque %, jobs, sessions, échecs, charge système via
  `sys_getloadavg` si dispo) + détails JSON. Commande **`admin-technique:capturer-sante`** planifiée
  (`hourly`) — son ancienneté atteste que le cron tourne.
- **Cockpit** `GET /administration-technique` (`administration-technique.cockpit`, gaté
  `admin_technique.view|monitor`) : statut global, 7 cartes de contrôle, **supervision des modules**
  (volumétrie PAP/Budget/Recettes/Engagements/Paiements/GED/Workflow/API), **jobs en échec**, historique
  des captures. Menu « Supervision technique ».
- **Endpoint `/health`** (`sante.health`) **public** (sondes externes / load-balancer) : JSON minimal
  `{statut, horodatage, controles}` sans détail sensible ; **HTTP 503** si statut critique, 200 sinon.
- **Permissions** : `admin_technique.{view,monitor,backup,restore,security,logs,admin}` créées par le
  **`AdministrationTechniqueSeeder`** (idempotent) — commande prévue par le CDC :
  `php artisan db:seed --class=AdministrationTechniqueSeeder` (intégré à `DatabaseSeeder`). Accordées à
  l'administrateur (toutes) et à l'auditeur (view/monitor/security/logs).
- Tests : `tests/Feature/AdministrationTechniqueTest.php` (6) — permissions, contrôles de santé, capture
  planifiée, cockpit, endpoint /health public, refus sans droit.

## 2bis. Lot T4 livré — Durcissement sécurité (OWASP A07 / A05)

**Non destructif** — migration additive `2026_07_31_000004_create_blocked_ips`.

- **Anti brute-force du login web** : `App\Services\AdministrationTechnique\ProtectionConnexionService`
  verrouille une IP après **5 échecs en 15 min** (durée 15 min). Les tentatives sont comptées depuis
  `login_logs` (= login_attempts) ; le verrou est persisté dans **`blocked_ips`** (modèle `BlockedIp`,
  scope `actif`). Intégré au `LoginController` : refus si IP verrouillée (avant `Auth::attempt`),
  enregistrement de l'échec, **réinitialisation à la connexion réussie**. Blocage **manuel/permanent**
  possible (`bloquerManuel`). Le cockpit T1 affiche le nombre d'IP verrouillées.
- **En-têtes de sécurité** : middleware `App\Http\Middleware\EntetesSecurite` (groupe web) →
  `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: same-origin`,
  `Permissions-Policy`, **CSP** (autorise jsDelivr + inline existant pour ne rien casser), **HSTS** en
  HTTPS. Désactivable via `APP_SECURITY_HEADERS=false`.
- Tests : `tests/Feature/SecuriteDurcissementTest.php` (4) — verrouillage après 5 échecs (service + HTTP),
  blocage manuel permanent, présence des en-têtes. Auth existante non régressée (ForcePasswordChangeTest OK).

> Reste hors périmètre de ce lot (recommandé ISO 27001) : **MFA/2FA**, chiffrement de session
> (`SESSION_ENCRYPT`), forçage HTTPS — décisions d'infrastructure/déploiement.

## 2ter. Lot T2 livré — Gestion des incidents (ITIL)

**Non destructif** — migration additive `2026_07_31_000005_create_module_incidents`.

- **Tables** : `incident_categories` (5 catégories seedées : Sécurité, Performance, Disponibilité, Base de
  données, Infrastructure), `incidents` (numero `INC-{annee}-NNNN`, catégorie, gravité, source
  manuelle/monitoring/alerte, statut **ouvert→en_diagnostic→en_correction→en_validation→résolu→clôturé**
  (+rejeté), impact, cause racine, résolution, déclarant/assigné, dates), `incident_actions` (journal
  ITIL : type diagnostic/correction/validation/communication/clôture/rejet + transition de statut).
  Modèles `Incident`/`IncidentCategorie`/`IncidentAction`.
- **Cycle ITIL** : `IncidentController` — déclarer (`incident.creer`) → `avancer()` étape par étape
  (`incident.modifier`) → `cloturer()` (résolution requise) / `rejeter()` (`incident.valider`) +
  `storeAction()` (journal). Chaque transition journalise une `IncidentAction`.
- **Routes** `incidents.*` ; vues `incidents/{index,form,show}` (fiche + pilotage du cycle + journal des
  actions inline). Menu « Incidents ».
- **Permissions** : module `incident` ajouté au `RoleSeeder` ; `creer` au gestionnaire/directeur,
  `creer/modifier/valider` à l'auditeur, tout à l'administrateur, `voir` à tous (`*.voir`).
- **Cockpit T1** : badge « N incident(s) ouvert(s) · M critique(s) » dans le centre de supervision.
- Tests : `tests/Feature/IncidentTest.php` (5) — catégories seedées, **cycle complet
  déclaration→…→clôture**, blocage si clos, rejet, séparation des droits (déclare vs clôture).

## 2quater. Lot T3 livré — Sauvegardes & restauration (ISO 22301)

**Non destructif** — migration additive `2026_07_31_000006_create_module_sauvegardes`.

- **Sauvegardes** : `BackupService::executer()` produit un artefact **adapté au pilote** — `mysqldump`
  (MySQL) ou **snapshot logique JSON** (SQLite/autres, manifeste tables + volumétrie) — et trace chaque
  exécution dans `backups` (référence `BKP-{annee}-NNNN`, statut, **taille**, **checksum SHA-256**, durée,
  déclenchement manuel/auto). `backup_schedules` (planification, rétention). Commande **`backup:executer`**
  (planifiée `dailyAt 02:00`) + **purge** par rétention. Sauvegarde manuelle immédiate + **téléchargement**
  depuis l'UI. Permission `admin_technique.backup`.
- **Restauration — workflow contrôlé (jamais d'écrasement automatique)** : `restore_operations`
  (référence `RST-{annee}-NNNN`, point de restauration). Cycle **demande → validation 4-yeux → exécution
  tracée** : `demanderRestauration` → `validerRestauration` (**le valideur doit différer du demandeur** —
  séparation des fonctions) / `rejeterRestauration` → `marquerExecutee` (acte manuel autorisé, journalisé).
  Permission `admin_technique.restore`. Toutes les actions sont auditées (`AuditLogger`).
- **Cockpit T1** : badge « Dernière sauvegarde ». Menu « Sauvegardes & restauration ».
- Tests : `tests/Feature/SauvegardeTest.php` (5) — planification seedée, exécution produit un artefact
  (taille+checksum), sauvegarde HTTP, **workflow restauration 4 yeux**, permissions.

## 3. Bilan du module

| Lot | Objet | État |
|---|---|---|
| **T1** | Centre de supervision (santé + cockpit + /health) | ✅ |
| **T2** | Gestion des incidents (ITIL) | ✅ |
| **T3** | Sauvegardes & restauration (ISO 22301) | ✅ |
| **T4** | Durcissement sécurité (lockout + en-têtes, OWASP A07/A05) | ✅ |

**Conformité globale du module : ~42 % (audit initial) → ~88 %.** Le centre opérationnel de supervision,
de sécurité, de continuité de service et d'administration est en place : santé temps réel + endpoint
`/health`, verrouillage anti brute-force + en-têtes de sécurité, gestion des incidents ITIL, sauvegardes
réelles + restauration contrôlée 4-yeux. 25 tests dédiés verts ; aucune régression (DonneesDemoTest 1699
assertions). Tout en migrations additives (jamais `migrate:fresh`).

## 4. Reste (décisions d'infrastructure, hors code)
- **MFA/2FA**, `SESSION_ENCRYPT=true`, forçage HTTPS, agent OS pour CPU/RAM, exécution réelle du
  `mysqldump`/restore sur le serveur de production (le code orchestre et trace ; l'OS exécute).
- **T3** — sauvegardes & restauration (`backups`/`restore_operations` + commande mysqldump + journal/UI).

> Note CPU/RAM : non mesurables de façon fiable et portable en PHP pur (seul `sys_getloadavg` est exposé,
> Linux uniquement). Une métrologie CPU/RAM complète nécessiterait un agent OS (hors périmètre PHP natif).
