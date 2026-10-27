# Plan d'implémentation — module Notifications

**Base :** `docs/audits/AUDIT_MODULE_NOTIFICATIONS.md` (anomalies A1 → A14).
**Principe :** conserver le dispatcher, les tables et les interfaces existantes ; compléter sans dupliquer (pas de table `notification_recipients` : la table Laravel `notifications` est déjà « une ligne par destinataire », avec `read_at` et `archive_at` individuels).

## 1. Composants

| Composant | Décision | Motif |
|---|---|---|
| `NotificationDispatcher` | **Conserver et compléter** (nature, ressource, état d'action, déduplication, diffusion) | Point d'entrée unique déjà adopté par tous les services |
| Table `notifications` | **Compléter** (colonnes) et `data` → `jsonb` sur PostgreSQL | Corrige A1, porte A2/A3 sans nouvelle table |
| `NotificationInboxService` | **Corriger** (filtres PostgreSQL) et **compléter** (filtres, non lue, synthèse) | A1, A9, A12 |
| `NotificationPreferenceService` | **Corriger** (obligatoires) | A8 |
| `WorkflowNotificationService` | **Compléter** (décisions, relances rattachées, rappels, escalade) | A4, A5, A6 |
| `TacheWorkflowService` / `TacheActionService` | **Compléter** (état d'action, réaffectation) | A2, A7 |
| `NotificationController` / API | **Compléter** (ouverture contrôlée, aperçu, non lue, détail, destination, synthèse) | A9, A11, A12 |
| Cloche Blade / Vue | **Refaire le composant en place** (panneau rapide, actualisation, temps réel) | A9, A10 |
| Correspondance aux services du référentiel | `NotificationService` ≙ `NotificationDispatcher` + `NotificationInboxService` ; `RecipientResolver` ≙ `WorkflowNotificationService::destinataires()` ; `TemplateService` ≙ `NotificationTemplate::appliquer` ; `DispatchService` ≙ dispatcher + `Email`/`SmsNotificationService` ; `PreferenceService` ≙ existant ; `ActionResolver` = **nouveau** `NotificationActionResolver` ; `Reminder`/`EscaladeService` = **nouveau** `NotificationRappelService` + `AlerteEscaladeService` existant ; `AuditService` ≙ `AuditNotificationService` | Pas de doublon |

## 2. Modèle de données (migration `2026_10_25_400000_notifications_conformite`)

| Colonne | Type | Usage |
|---|---|---|
| `nature` | string(20), défaut `information` | action / information / alerte / rappel / systeme |
| `action_statut` | string(20), nullable | en_attente / traitee / annulee / expiree (null : pas d'action) |
| `action_traitee_le` | timestamp nullable | date de clôture de l'action |
| `ressource_type`, `ressource_id`, `reference` | string / bigint / string | dossier lié (RN-03) |
| `cle_deduplication` | string(150) nullable | idempotence (RN-10) |

Index : `(notifiable_type, notifiable_id, read_at)`, `(notifiable_id, action_statut)`, `(ressource_type, ressource_id)`, `(notifiable_id, cle_deduplication)`. PostgreSQL : `data` `text` → `jsonb` (réversible). Rétro-alimentation des 475 notifications (nature, état d'action, ressource) sans toucher à leur contenu.

## 3. Ordre d'intervention

1. Migration + modèle de priorité (criticité → P1…P4) + configuration (`config/notifications.php` : rappels, escalade, obligatoires, actualisation).
2. Dispatcher : nouveaux attributs, déduplication, diffusion `broadcast` si un diffuseur réel est configuré.
3. Moteur : notifications de décision (rejet, retour, validation finale) ; état d'action tenu par la clôture des tâches ; relances rattachées ; réaffectation.
4. Rappels/escalade : `NotificationRappelService`, commande `workflow:relances` horaire.
5. Préférences : obligatoires non désactivables.
6. Consultation : filtres serveur, non lue, synthèse, ouverture contrôlée (`NotificationActionResolver`), API.
7. Interfaces : cloche + panneau (Blade, Vue), page Notifications (filtres, états), SPA.
8. Paiement partiel : montant payé et solde.
9. Tests (fonctionnels, sécurité, intégration), non-régression, empreintes après, rapports.

## 4. Tests de non-régression

Suites existantes : `Notification*`, `Workflow*`, `MesTaches*`, `Depense*`, `ChaineDepense*`, `Alerte*`, `Recettes*`, `Ged*`, `Budget*`, `Pap*` ; comparaison à la référence des échecs préexistants (`base_lf.txt`). Nouvelle suite `NotificationsConformiteTest` couvrant NOT-001 → NOT-020 du plan de recette.
