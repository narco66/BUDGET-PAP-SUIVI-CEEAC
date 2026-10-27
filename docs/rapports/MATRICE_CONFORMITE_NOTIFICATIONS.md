# Matrice de conformité — module Notifications (état final)

**Référentiel :** `docs/Module_Notifications_BUDGET_CEEAC_GESBUDEP.md` · **Date :** 25 octobre 2026
**État initial :** `docs/audits/AUDIT_MODULE_NOTIFICATIONS.md` § 5.
Légende : ✅ conforme et vérifié par test · 🟡 partiel (écart documenté) · ➖ hors périmètre justifié.

## 1. Exigences fonctionnelles

| ID | Exigence | Initial | Final | Preuve (test / vérification) |
|---|---|---|---|---|
| NOT-001 | Notification automatique sur événement confirmé (RN-01) | 🟡 | ✅ | Étape, décision (rejet, retour, validation finale), réaffectation, signature ORD → `NotificationsConformiteTest::test_not001…`, `test_not003…`, `test_not013…` ; émission dans la transaction métier (annulée avec elle), canaux externes et diffusion après commit |
| NOT-002 | Notification cliquable vers le dossier exact (§ 9) | 🟡 | ✅ | `NotificationActionResolver::destination()` ; `test_not009…` (redirection exacte), `test_not011…` (action obsolète → dossier en consultation + message) ; vérifié sur PostgreSQL |
| NOT-003 | Compteur non lu | 🟡 | ✅ | Cloche Blade + panneau (`notifications.apercu`), compteur Vue actualisé ; `test_not009_not010…` |
| NOT-004 | Habilitation à l'ouverture (RN-04) | ✅ | ✅ | Notification du seul destinataire (404 sinon), page cible soumise à ses contrôles ; `test_not012…` |
| NOT-005 | Typologie action / information / alerte / rappel / système (§ 3) | ❌ | ✅ | Colonne `nature` + contrainte CHECK (PostgreSQL) ; 475 notifications rétro-alimentées |
| NOT-006 | Priorités P1-P4 (§ 4) | 🟡 | ✅ | `NotificationCriticite::priorite()` ; criticité par type paramétrable (`notification_types.criticite_defaut`) ; filtre « Priorité » |
| NOT-007 | Lu / non lu indépendant de l'action (§ 10, RN-05) | ❌ | ✅ | Colonnes `action_statut`, `action_traitee_le` ; « Marquer non lue » web/API ; `test_marquer_non_lue…`, `test_not009…` (lire ≠ traiter) |
| NOT-008 | Action obsolète inexécutable (RN-06) | 🟡 | ✅ | Clôture par Mes Tâches (`traitee` / `annulee`), recalcul à l'ouverture, actions hors circuit closes au démarrage du circuit ; le moteur refuse toujours l'action ; `test_not002…`, `test_not011…`, `test_not019…` |
| NOT-009 | Destinataires selon rôle, affectation, délégation (§ 5) | ✅ | ✅ | `WorkflowNotificationService::destinataires()` conservé |
| NOT-010 | EB PAP / hors PAP (§ 6.1-6.2) | 🟡 | ✅ | Étapes + décisions à l'initiateur ; vérifié sur PostgreSQL (EB hors PAP : Directeur → SG notifié) |
| NOT-011 | Engagement (§ 6.3) | 🟡 | ✅ | Étapes Direction du Budget / CF, rejet/retour à l'initiateur, échec de soumission automatique (alerte), insuffisance de crédits (alertes `alertes:detecter-budget`) |
| NOT-012 | Liquidation (§ 6.4) | 🟡 | ✅ | Étapes certification / CF, liquidation générée, décisions |
| NOT-013 | Ordonnancement : seuil paramétré, Agence comptable (§ 6.5) | 🟡 | ✅ | `test_not006_not007…` (≤ 5 000 000 → SG, > → Président) ; `test_not013…` (Agence comptable notifiée, action close au démarrage du paiement) |
| NOT-014 | Paiement partiel et solde (§ 6.6, RN-17) | 🟡 | ✅ | `test_not008…` : « Paiement partiel validé », solde restant dû, Agence comptable informée ; « intégralement réglé » seulement si solde nul |
| NOT-015 | Autres modules (§ 7) | 🟡 | 🟡 | Budget, PAP, recettes, reporting, S&E, GED, RBM déjà notifiés ; 14 notifications GED/PAP/budget/RBM restent hors dispatcher (N7, inchangé) |
| NOT-016 | Cloche + panneau rapide (§ 8.1-8.2) | ❌ | ✅ | Panneau : titre, message, module, priorité, référence, état de lecture et d'action, « Tout marquer comme lu », « Voir toutes » ; `test_cloche_et_panneau…` |
| NOT-017 | Page complète, filtres et recherche serveur (§ 8.3) | ❌ (500 PG) | ✅ | `data` en jsonb ; filtres lecture, nature, priorité, module, type, action, période, recherche (référence/titre/message) ; `test_page_notifications_filtres…` ; vérifié en 200 sur PostgreSQL |
| NOT-018 | Actions lu / non lu, tout lu, archiver (§ 8.4) | 🟡 | ✅ | Web + API ; `test_not012_not016…` |
| NOT-019 | Synchronisation Mes Tâches (§ 11, RN-16) | 🟡 | ✅ | Création → notification ; décision → action close + étape suivante ; réaffectation → nouveau responsable notifié (`test_not018…`) ; compteurs indépendants |
| NOT-020 | Temps réel + reprise (§ 12, RN-12, RN-13) | 🟡 | 🟡 | Événement `NotificationCreee` sur canal privé, après commit, si un diffuseur réel est configuré (`test_diffusion…`) ; actualisation périodique de repli (60 s) et au retour sur l'onglet. **Reverb non démarré en local** (`BROADCAST_CONNECTION=log`) : la diffusion directe n'a pas été vérifiée de bout en bout |
| NOT-021 | Après commit, idempotence, déduplication (RN-10) | 🟡 | ✅ | `cle_deduplication` ; `test_not015…` ; commande de rétro-alimentation idempotente (`test_not020…`) |
| NOT-022 | Rappels 24 h / 48 h, retard, escalade, arrêt après traitement (§ 17, RN-09) | 🟡 | ✅ | `NotificationRappelService` (config `notifications.rappels`, jours ouvrables, fuseau institutionnel) ; `test_not017…` (a, b, c) ; tâche planifiée horaire |
| NOT-023 | Obligatoires non désactivables (§ 19) | ❌ | ✅ | Canal in-app forcé pour critiques/urgences et actions ; `test_notifications_obligatoires…` |
| NOT-024 | API (§ 14.2) | 🟡 | ✅ | `summary`, `{id}`, `{id}/destination`, `{id}/unread`, formes PATCH `read`, `read-all`, `archive` |
| NOT-025 | Journalisation (§ 16, RN-11) | ✅/🟡 | ✅ | `audit_notifications` : envoi, consultation, échec de diffusion ; escalades au journal central (`notification_escalade`) ; rappels tracés sur la tâche |
| NOT-026 | Indicateurs (§ 20) | 🟡 | 🟡 | Non lues, actions en attente, critiques, taux de lecture, ventilation par module (API) ; délais événement→livraison et création→première lecture non calculés |
| NOT-027 | Paramétrage administrable versionné (§ 18) | 🟡 | 🟡 | Types, canaux, modèles administrables ; délais de rappel et obligatoires en configuration versionnée (Git), pas d'écran dédié |
| NOT-028 | Canaux WebSocket privés (§ 15) | ➖ | ✅ | `App.Models.User.{id}` réservé à son titulaire (`routes/channels.php`) |
| NOT-029 | Préservation de l'existant (§ 22) | ✅ | ✅ | Empreintes avant/après : `docs/audits/notifications/` |
| NOT-030 | Charte (RN-20) | ✅ | ✅ | Bootstrap 5 + Bootstrap Icons conservés (pas de Font Awesome ni de React : stack réelle Blade + Vue 3) |

## 2. Règles RN-01 → RN-20

| Règle | État | Mise en œuvre |
|---|---|---|
| RN-01 après confirmation | ✅ | Émission dans la transaction métier (rollback commun) ; e-mail/SMS/diffusion après commit |
| RN-02 source identifiable | ✅ | `type_code`, `module`, méta (circuit, instance, étape, tâche) |
| RN-03 ressource précise | ✅ | `ressource_type`, `ressource_id`, `reference` |
| RN-04 contrôle à l'ouverture | ✅ | Propriétaire vérifié, page cible contrôlée |
| RN-05 lire ≠ traiter | ✅ | États indépendants |
| RN-06 action obsolète | ✅ | Clôture + recalcul + message |
| RN-07 destinataires réels | ✅ | Résolution existante |
| RN-08 cloisonnement | ✅ | Idem ; escalade limitée au responsable hiérarchique |
| RN-09 arrêt des rappels | ✅ | Tâches ouvertes uniquement |
| RN-10 idempotence | ✅ | Clé de déduplication |
| RN-11 auditabilité | ✅ | `audit_notifications` + journal central |
| RN-12 panne sans perte | ✅ | Notifications de décision et réaffectation isolées (point de sauvegarde) ; diffusion protégée |
| RN-13 récupération | ✅ | Persistance PostgreSQL + actualisation périodique |
| RN-14 règles paramétrables | 🟡 | Types/canaux/modèles en base ; délais en configuration |
| RN-15 préservation | ✅ | Empreintes identiques |
| RN-16 synchronisation Mes Tâches | ✅ | Voir NOT-019 |
| RN-17 partiel ≠ total | ✅ | Voir NOT-014 |
| RN-18 seuil ORD | ✅ | `SeuilOrdonnancementService` (paramètre puis configuration) |
| RN-19 confidentialité | ✅ | Notification du seul destinataire ; diffusion minimale ; aperçu sans données au-delà du message |
| RN-20 charte | ✅ | Voir NOT-030 |
