# Audit du module Notifications — BUDGET-CEEAC / GESBUDEP

**Date :** 25 octobre 2026
**Référentiel :** `docs/Module_Notifications_BUDGET_CEEAC_GESBUDEP.md` (v1.0)
**Environnement audité :** dépôt local (branche `audit/chaine-depense-2026`, 203 fichiers modifiés non validés, aucun écrasé) ; PostgreSQL 18.4 (127.0.0.1:5433, base `gest_budget_pap`) ; Laravel 13 / PHP 8.3.
**Audit précédent :** `docs/audit-notifications.md` (constats N1 → N11, corrigés le même jour sauf N7, N8, N10). Le présent audit en repart et le confronte au nouveau référentiel.

## 1. État de référence

| Élément | Constat |
|---|---|
| Processus concurrents | Aucun processus de migration, seeder, test ou file d'attente en cours. Seuls tournent des serveurs de développement (`artisan serve`, Vite), sans interférence : laissés en place. |
| Données protégées | Empreintes SHA-256 de 21 catégories enregistrées dans `docs/audits/notifications/empreintes_avant.json` (utilisateurs, habilitations, organigramme, nomenclature, Budget 2026, dossiers EB/ENG/LIQ/ORD/PAY, GED, journal). Le script `database/scripts/empreintes_donnees_protegees.php` a été étendu à la chaîne de dépense, à la GED et au journal. |
| Journal central | 3 071 écritures (id max 7 364) au début de l'intervention. |
| Notifications | 475 notifications, 475 journaux d'envoi, 490 événements d'audit notifications, 15 tâches en file. |

## 2. Architecture réellement en place

> **Écart de stack.** Le référentiel cible React + Font Awesome 7. L'application utilise en réalité **Blade + Bootstrap 5 + Bootstrap Icons** pour l'interface principale et un **SPA Vue 3** (`frontend/`) pour l'API. Conformément à la consigne « ne pas détruire pour reconstruire », l'implémentation s'appuie sur ces deux interfaces : aucune réécriture en React, aucun changement de bibliothèque d'icônes (charte existante conservée, exigence RN-20).

| Couche | Composants existants |
|---|---|
| Stockage | Table Laravel `notifications` (UUID, `data` **en text**, `read_at`, + `type_code`, `module`, `criticite`, `archive_at`) ; `notification_types` (22), `notification_templates` (22), `notification_channels` (16), `notification_logs` (journal d'envoi), `audit_notifications` (registre d'audit), `email_logs`, `sms_logs`, `relance_rules` (5), `relance_logs`, `alertes`. Préférences dans `users.preferences_notifications` (JSON). |
| Émission | `NotificationDispatcher::envoyer()` : point d'entrée unique (in-app, e-mail et SMS après commit, journal, audit, préférences, modèles). Services métier : `WorkflowNotificationService` (étape en attente, relance SLA), `DepenseNotificationService` (accusés au demandeur), `Budget`/`Pap`/`Recettes`/`Reporting`/`SuiviEval`NotificationService, `AlerteRegistryService`, `AlerteEscaladeService`, `RelanceMetierService`. |
| Classe Laravel | `InstitutionInAppNotification` : canal `database` uniquement (pas de `broadcast`). |
| Consultation | Web : `NotificationController` (index, lue, ouvrir, tout-lu, archiver) + vue `notifications/index.blade.php`. API : `NotificationsApiController` (`/api/v1/notifications` : index, hub, unread-count, read, tout-lu, archiver, préférences, modèles, alertes). SPA : `NotificationsView.vue`, compteur dans `AppLayout.vue`. |
| Cloche | Blade : simple lien vers la page avec compteur calculé au rendu (pas de panneau, pas d'actualisation). Vue : lien + compteur chargé une fois au montage. |
| Temps réel | `laravel/reverb` installé, `resources/js/echo.js` (cockpit uniquement), `BROADCAST_CONNECTION=log`. Aucun canal utilisateur pour les notifications. |
| Asynchrone | `QUEUE_CONNECTION=database`, e-mails en file (`SendInstitutionEmailJob`), planificateur : `workflow:relances` (quotidien 08:00), `alertes:escalader`, `relances:metier`, `notifications:retry-email`, `notifications:purge-archives`. |
| Mes Tâches | `TacheWorkflowService` crée/clôt les tâches ; depuis ce jour `cloreNotifications()` marque lues et « traitées » les notifications d'une tâche close (méta JSON `tache_traitee`). |
| Paramètres | Seuil ORD paramétré (`SeuilOrdonnancementService`, table de paramètres puis `chaine_depense.ordonnancement.seuil_xaf` = 5 000 000) : conforme. |

## 3. Anomalies constatées

| # | Anomalie | Gravité | Preuve |
|---|---|---|---|
| A1 | **Recherche et filtre par module/type en erreur 500 sur PostgreSQL** : `data->titre`, `data->module` sont compilés en `"data"->>'titre'` alors que `notifications.data` est de type `text`. | **Bloquant** | `SQLSTATE[42883] l'opérateur n'existe pas : text ->> text` reproduit sur la base. |
| A2 | Aucun **état d'action** distinct de la lecture : impossible de savoir si une notification « à traiter » l'est encore ; l'état « traité » n'existe que dans une méta JSON posée aujourd'hui. Exigences §10, RN-05, RN-06. | Majeur | Schéma `notifications`. |
| A3 | Pas de **nature** (action / information / alerte / rappel / système), pas de **priorité P1–P4** exposée, pas de **ressource** (type/id/référence) ni de **clé de déduplication**. | Majeur | Schéma ; §3, §4, §13, RN-03, RN-10. |
| A4 | **Rejet, retour pour correction et validation finale non notifiés à l'initiateur** pour les circuits pilotés par le moteur (EB, ENG, LIQ, ORD, PAY) : le type `workflow.decision` existe mais n'est jamais émis par le moteur. | Majeur | 0 notification `workflow.decision` en base ; `WorkflowService::apresRejet/apresRenvoi` sans notification. |
| A5 | **Relances SLA non rattachées à l'étape/tâche** (méta sans `etape_ordre`/`tache_id`) : elles restent « à traiter » après décision et ouvrent le dossier au lieu de la fiche de traitement. | Majeur | `WorkflowNotificationService::notifierRelance`. |
| A6 | **Rappels 24 h / 48 h et escalade hiérarchique des tâches absents** : seule existe une relance après échéance, tous les N jours (codée sur `config('workflow.relance.intervalle_jours')`). | Majeur | §17, RN-09. |
| A7 | **Réaffectation d'une tâche non notifiée** au nouveau responsable. | Moyen | `TacheActionService::reaffecter`. |
| A8 | **Alertes critiques et actions institutionnelles désactivables** : la préférence « désactiver toutes les notifications » supprime aussi les critiques (constat N8 toujours ouvert). | Moyen | `NotificationPreferenceService::canauxEffectifs` retourne `[]`. |
| A9 | **Cloche sans panneau rapide ni actualisation** (Blade et Vue) ; pas de « marquer non lu » ; filtres nature/priorité/action/période absents ; SPA sans filtre ni ouverture du dossier. | Majeur (ergonomie) | §8.1 → §8.4. |
| A10 | **Pas de diffusion temps réel** des notifications (canal `database` seul, pas de canal privé utilisateur). | Moyen | §12 ; la persistance PostgreSQL assure la consultation (RN-12, RN-13). |
| A11 | **Ouverture d'une notification obsolète** : redirige vers la fiche sans signaler que l'action n'est plus disponible ; consultation non tracée. | Moyen | `NotificationController::ouvrir`. |
| A12 | **API incomplète** : pas de synthèse, détail, « non lue », résolution de destination. | Moyen | §14.2. |
| A13 | 14 notifications GED/PAP/budget/RBM hors dispatcher (N7) ; téléphones et expéditeur à renseigner (N10). | Moyen / exploitation | Audit précédent. |
| A15 | **Signature de l'ordonnancement non notifiée à l'Agence comptable** : le dossier de paiement est créé en brouillon sans aucune notification (constaté en préparant les tests). | Majeur | `OrdonnancementVersPaiementService::assurerDossierDepuisOrdreSigne` ; § 6.5-6.6. |
| A14 | **Paiement partiel** : l'avis de paiement n'indique ni le montant payé ni le solde restant. | Moyen | `DepenseNotificationService::notifierPaiementValide` ; RN-17. |

## 4. Points conformes (à conserver)

- Dispatcher unique, journal d'envoi corrélé (identifiant fixé avant envoi), audit `audit_notifications`, e-mail/SMS après commit, réessais e-mail (`notifications:retry-email`), modèles par type et canal.
- Destinataires d'étape : responsable de la structure validatrice + délégataires/intérimaires en cours, sinon titulaires du rôle filtrés (contrôle Direction du Budget), initiateur exclu (séparation des fonctions) — RN-07, RN-08.
- Liens stockés relatifs, ouverture limitée aux liens internes (pas de redirection ouverte), contrôle d'appartenance (`$user->notifications()`) : pas d'IDOR sur lecture/archivage.
- Notification d'étape émise dans la transaction du moteur : annulée avec elle (RN-01 pour le canal in-app) ; canaux externes après commit.
- Seuil d'ordonnancement paramétré ; circuits `ORDONNANCEMENT` (SG) / `ORDONNANCEMENT_PRESIDENT`.
- Synchronisation Mes Tâches : création de tâche → notification de l'étape ; tâche close → notification marquée traitée (depuis ce jour).

## 5. Matrice de conformité initiale

Légende : ✅ conforme · 🟡 partiel · ❌ absent/non conforme · ➖ hors périmètre justifié.

| ID | Exigence (référentiel) | État actuel | Écart | Correction | Priorité |
|---|---|---|---|---|---|
| NOT-001 | Notification automatique sur événement confirmé (§2, RN-01) | 🟡 | Étapes notifiées ; décisions (rejet/retour/validation finale) non notifiées | Notifications de décision du moteur (A4) | Haute |
| NOT-002 | Notification cliquable vers le dossier exact (§9) | 🟡 | Lien direct OK ; relances vers le dossier ; obsolescence non signalée | Résolveur de destination, message d'action obsolète (A5, A11) | Critique |
| NOT-003 | Compteur non lu (§8.1) | 🟡 | Calculé au rendu, sans actualisation | Cloche + panneau + actualisation périodique / temps réel (A9) | Haute |
| NOT-004 | Respect des habilitations à l'ouverture (RN-04) | ✅ | La page cible applique ses contrôles ; la notification n'ouvre aucun droit | Conserver ; tracer la consultation | Critique |
| NOT-005 | Typologie action/information/alerte/rappel/système (§3) | ❌ | Pas de nature | Colonne `nature` + rétro-alimentation (A3) | Haute |
| NOT-006 | Priorités P1–P4 configurables (§4) | 🟡 | Criticité par type paramétrable, pas d'échelle P1–P4 | Correspondance criticité → P1–P4, filtre | Moyenne |
| NOT-007 | États lu / non lu indépendants de l'action (§10, RN-05) | ❌ | Pas d'état d'action, pas de « non lue » | `action_statut`, `action_traitee_le`, marquer non lue (A2) | Critique |
| NOT-008 | Action obsolète inexécutable (RN-06, NOT-011/019 du plan de tests) | 🟡 | Moteur refuse l'action ; notification non mise à jour hors tâches | État d'action tenu par Mes Tâches et le moteur | Critique |
| NOT-009 | Destinataires selon rôle, affectation, délégation (§5) | ✅ | — | Conserver | Critique |
| NOT-010 | EB PAP / hors PAP (§6.1-6.2) | 🟡 | Étapes notifiées ; retour/rejet/validation finale non notifiés | A4 | Haute |
| NOT-011 | ENG : création, visa, rejet, insuffisance de crédits (§6.3) | 🟡 | Visa → LIQ notifié ; rejet non notifié ; crédits : alerte quotidienne + échec de soumission | A4 | Haute |
| NOT-012 | LIQ : certification, visa CF, rejet (§6.4) | 🟡 | Idem | A4 | Haute |
| NOT-013 | ORD : seuil paramétré SG / Président, notification Agence comptable (§6.5) | 🟡 | Seuil conforme ; **Agence comptable non notifiée** à la signature (A15) | Notification « paiement à préparer » | Haute |
| NOT-014 | PAY : partiel et solde (§6.6, RN-17) | 🟡 | Montant/solde absents de l'avis | Enrichir l'avis (A14) | Haute |
| NOT-015 | Autres modules (§7) | 🟡 | Budget, PAP, recettes, reporting, S&E, GED, RBM couverts ; 14 hors dispatcher (N7) | Documenté, hors lot | Moyenne |
| NOT-016 | Cloche + panneau rapide (§8.1-8.2) | ❌ | Lien seul | Panneau Blade + Vue (A9) | Haute |
| NOT-017 | Page complète : filtres serveur nature/priorité/module/période/action, recherche (§8.3) | ❌ | Recherche et filtre module en erreur 500 sur PostgreSQL | `data` en jsonb, filtres serveur (A1, A9) | **Critique** |
| NOT-018 | Actions : lu/non lu, tout lu, archiver (§8.4) | 🟡 | Pas de « non lue » | Ajout | Moyenne |
| NOT-019 | Synchronisation Mes Tâches (§11, RN-16) | 🟡 | Clôture OK ; réaffectation non notifiée | A7 | Haute |
| NOT-020 | Temps réel + reprise (§12, RN-12, RN-13) | 🟡 | Persistance OK, pas de diffusion | Canal privé `broadcast` conditionnel + actualisation périodique de repli (A10) | Moyenne |
| NOT-021 | Publication après commit, idempotence, déduplication (§12.3, RN-10) | 🟡 | Externes après commit ; pas de déduplication | Clé de déduplication | Haute |
| NOT-022 | Rappels 24 h / 48 h, retard, escalade configurables, arrêt après traitement (§17, RN-09) | 🟡 | Relance post-échéance seulement | Service de rappels/escalade paramétré (A6) | Haute |
| NOT-023 | Notifications obligatoires non désactivables (§19) | ❌ | N8 | Canal in-app forcé pour critiques et actions (A8) | Moyenne |
| NOT-024 | API (§14.2) | 🟡 | Synthèse, détail, non lue, destination absents | Compléter (A12) | Moyenne |
| NOT-025 | Journalisation (§16, RN-11) | ✅/🟡 | `audit_notifications` + `notification_logs` ; consultation non tracée | Tracer consultation, rappels, escalades ; actes sensibles au journal central | Moyenne |
| NOT-026 | Indicateurs (§20) | 🟡 | Non lues / jour / critiques | Ajouter actions en attente, taux de lecture, délai de première lecture | Moyenne |
| NOT-027 | Paramétrage administrable versionné (§18) | 🟡 | Types, canaux, modèles administrables ; délais en configuration | Délais de rappel dans `config/notifications.php` (versionné par Git) | Faible |
| NOT-028 | Sécurité canaux WebSocket (§15) | ➖ | Aucun canal utilisateur | Canal privé `App.Models.User.{id}` limité au propriétaire | Moyenne |
| NOT-029 | Préservation de l'existant (§22, RN-15) | ✅ | — | Empreintes avant/après | Critique |
| NOT-030 | Charte et maquettes (RN-20) | ✅ | — | Conserver Bootstrap/Bootstrap Icons | Haute |

## 6. Risques de régression

- Passage de `notifications.data` en `jsonb` : Laravel lit/écrit le champ par le cast `array` → transparent ; migration réversible (`jsonb` → `text`). Les requêtes `data::json` existantes restent valides.
- Ajout de notifications de décision : volumétrie accrue pour les initiateurs → déduplication et nature « information ».
- Rappels : planifier `workflow:relances` toutes les heures ; risque de sur-sollicitation maîtrisé par le suivi des rappels envoyés (événements de tâche) et l'arrêt dès la clôture.
- Tests existants s'appuyant sur le nombre exact de notifications : à surveiller lors de la non-régression.

## 7. Fichiers concernés

`app/Services/Notifications/{NotificationDispatcher,NotificationInboxService,NotificationPreferenceService,DepenseNotificationService}.php`, `app/Services/Workflow/{WorkflowNotificationService,WorkflowRelanceService,TacheWorkflowService,TacheActionService}.php`, `app/Services/WorkflowService.php`, `app/Notifications/InstitutionInAppNotification.php`, `app/Http/Controllers/NotificationController.php`, `app/Http/Controllers/Api/V1/NotificationsApiController.php`, `resources/views/layouts/app.blade.php`, `resources/views/notifications/index.blade.php`, `frontend/src/layouts/AppLayout.vue`, `frontend/src/views/NotificationsView.vue`, `routes/{web,api,channels}.php`, `config/notifications.php`, `bootstrap/app.php`, nouvelle migration.

## 8. Priorités d'implémentation

1. **Critique** : A1 (erreur 500), A2/A3 (état d'action, nature, ressource, déduplication), A11 (action obsolète).
2. **Haute** : A4 (décisions), A5 (relances rattachées), A6 (rappels/escalade), A9 (cloche, panneau, page), A14 (paiement partiel), A7 (réaffectation).
3. **Moyenne** : A8 (obligatoires), A10 (temps réel), A12 (API), indicateurs.
4. **Documenté, hors lot** : A13 (N7, N10).
