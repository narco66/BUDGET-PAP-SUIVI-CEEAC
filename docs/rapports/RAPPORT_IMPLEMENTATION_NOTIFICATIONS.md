# Rapport d'implémentation — module Notifications

**Application :** BUDGET-CEEAC / GESBUDEP · **Date :** 25 octobre 2026
**Référentiel :** `docs/Module_Notifications_BUDGET_CEEAC_GESBUDEP.md`
**Livrables liés :** `docs/audits/AUDIT_MODULE_NOTIFICATIONS.md`, `docs/audits/PLAN_IMPLEMENTATION_NOTIFICATIONS.md`, `docs/rapports/MATRICE_CONFORMITE_NOTIFICATIONS.md`, `docs/rapports/RAPPORT_TESTS_NOTIFICATIONS.md`, `docs/rapports/RAPPORT_NON_REGRESSION_NOTIFICATIONS.md`.

## 1. État initial

Un socle solide existait déjà : un dispatcher unique (in-app, e-mail, SMS, journal d'envoi, audit, préférences, modèles), une résolution fine des destinataires d'étape (structure validatrice, délégations, intérims, séparation des fonctions) et la synchronisation de base avec Mes Tâches. Mais :

- la **recherche et le filtre par module de la page Notifications échouaient en erreur 500 sur PostgreSQL** (colonne `data` en `text`) ;
- il n'existait **ni état d'action, ni nature, ni dossier lié, ni déduplication** ;
- les **décisions** (rejet, retour, validation finale) n'étaient **pas notifiées à l'initiateur** ;
- la **signature de l'ordonnancement n'était pas notifiée à l'Agence comptable** ;
- **rappels 24 h / 48 h et escalade** absents, relances SLA non rattachées à la tâche ;
- **cloche sans panneau ni actualisation**, pas de « non lue », pas de diffusion temps réel ;
- **titre des notifications écrasé par le message** dès qu'un modèle avait un sujet (défaut découvert pendant les tests).

## 2. Anomalies identifiées et corrections

| # | Anomalie | Correction | Fichiers |
|---|---|---|---|
| A1 | Erreur 500 recherche / filtre module (PostgreSQL) | `data` → `jsonb` ; filtres sur colonnes dédiées ; recherche portable `LOWER(data->>'titre') LIKE` | migration `2026_10_25_400000`, `NotificationInboxService` |
| A2/A3 | Pas d'état d'action, de nature, de dossier, de déduplication | Colonnes `nature`, `action_statut`, `action_traitee_le`, `ressource_type/id`, `reference`, `cle_deduplication` + index + contraintes CHECK ; rétro-alimentation | migration, `NotificationNature`, `NotificationDispatcher`, `notifications:retroalimenter` |
| A4 | Décisions non notifiées à l'initiateur | `WorkflowNotificationService::notifierDecision()` appelé par le moteur (rejet, retour, validation finale), isolé par point de sauvegarde | `WorkflowService`, `WorkflowNotificationService` |
| A5 | Relances non rattachées | Méta `etape_ordre`/`tache_id`, lien vers la fiche de traitement, action close avec la décision | `WorkflowNotificationService::notifierRelance` |
| A6 | Rappels / escalade absents | `NotificationRappelService` : rappel 1 (24 h), rappel 2 (48 h), escalade au responsable hiérarchique après N jours de retard, jours ouvrables, fuseau institutionnel, traçabilité sur la tâche ; `workflow:relances` horaire | `NotificationRappelService`, `WorkflowRelancesCommand`, `bootstrap/app.php`, `config/notifications.php` |
| A7 | Réaffectation non notifiée | Nouveau responsable notifié (action), ancien informé | `TacheActionService`, `WorkflowNotificationService::notifierReaffectation` |
| A8 | Obligatoires désactivables (N8) | Canal in-app forcé pour critiques/urgences et actions | `NotificationPreferenceService` |
| A9 | Cloche sans panneau, page incomplète | Panneau rapide Blade, compteur Vue actualisé, page enrichie (filtres, états, non lue), SPA (filtres, pagination, ouverture contrôlée) | `layouts/app.blade.php`, `notifications/index.blade.php`, `NotificationsView.vue`, `AppLayout.vue` |
| A10 | Pas de temps réel | Événement `NotificationCreee` (ShouldBroadcastNow) sur `private-App.Models.User.{id}`, après commit, uniquement vers un diffuseur réel ; écoute Echo dans la cloche si Reverb est actif | `Events/NotificationCreee`, `routes/channels.php` |
| A11 | Ouverture d'une notification obsolète | `NotificationActionResolver` : état recalculé, dossier ouvert en consultation, message « déjà traité par … », consultation tracée | `NotificationController::ouvrir`, `NotificationActionResolver` |
| A12 | API incomplète | `summary`, `{id}`, `{id}/destination`, `{id}/unread`, formes PATCH | `NotificationsApiController`, `routes/api.php` |
| A14 | Paiement partiel sans solde | Montant payé, montant réglé, solde restant dû ; Agence comptable informée du solde | `DepenseNotificationService::notifierPaiementValide` |
| A15 | Signature ORD non notifiée à l'Agence comptable | `notifierPaiementAPreparer` (action) à la création du dossier de paiement ; close au démarrage du circuit de paiement | `OrdonnancementVersPaiementService`, `NotificationActionResolver::cloreActionsHorsCircuit` |
| A16 | Titre remplacé par le message | Le sujet du modèle est appliqué comme gabarit distinct ; 16 titres réparés en base | `NotificationTemplate::appliquer`, `NotificationDispatcher` |
| — | Synthèse API en 500 sur PostgreSQL (`GROUP BY` + tri implicite de la relation) | `reorder()` | `NotificationsApiController::summary` |

## 3. Fonctionnalités ajoutées

- Nature et priorité P1–P4, état d'action indépendant de la lecture, dossier lié et référence sur chaque notification.
- Notifications de décision à l'initiateur, de réaffectation, de paiement à préparer, de paiement partiel.
- Rappels, alerte de retard (existante, désormais rattachée), escalade hiérarchique.
- Résolution sécurisée de la destination, message d'action obsolète, consultation tracée.
- Cloche avec panneau rapide, actualisation périodique et temps réel (si diffuseur actif).
- Page Notifications : filtres nature, priorité, action, période ; recherche par référence ; « Marquer non lue » ; indicateurs (actions à traiter, taux de lecture).
- API : synthèse, détail, destination, non lue, formes PATCH.
- Commande `notifications:retroalimenter [--simulation]` (idempotente).

## 4. Fonctionnalités conservées

Dispatcher, journal d'envoi, registre `audit_notifications`, e-mail/SMS après commit et réessais, modèles et canaux par type, préférences, résolution des destinataires, alertes institutionnelles et leur escalade (`alertes:escalader`), relances métier, purge des archives, routes existantes (web et API) — aucune supprimée ni renommée.

## 5. Modifications de base de données

Migration `2026_10_25_400000_notifications_conformite` (réversible), appliquée sur PostgreSQL 18 après sauvegarde `pg_dump` des tables `notifications` et `notification_logs` :

- 7 colonnes ajoutées à `notifications`, 4 index, 2 contraintes CHECK (PostgreSQL) ;
- `notifications.data` : `text` → `jsonb` (retour possible par `migrate:rollback`).

Données : 475 notifications rétro-alimentées (164 informations, 308 actions, 3 alertes ; 10 actions encore en attente — toutes sur des tâches ouvertes —, 298 traitées) ; 466 rattachées à leur dossier ; 16 titres réparés. 5 types ajoutés au référentiel (`workflow.escalade`, `workflow.reaffectation`, `depense.liquidation_generee`, `depense.engagement_non_soumis`, `depense.paiement_a_preparer`) par `firstOrCreate`, sans modifier les types ni les modèles existants. Aucune autre table modifiée.

## 6. Fichiers créés et modifiés

**Créés :** `app/Support/NotificationNature.php`, `app/Services/Notifications/NotificationActionResolver.php`, `app/Services/Notifications/NotificationRappelService.php`, `app/Events/NotificationCreee.php`, `app/Console/Commands/NotificationsRetroalimenterCommand.php`, `database/migrations/2026_10_25_400000_notifications_conformite.php`, `tests/Feature/NotificationsConformiteTest.php`, les six livrables documentaires, `docs/audits/notifications/empreintes_{avant,apres}.json`.

**Modifiés :** `app/Support/NotificationCriticite.php`, `app/Models/NotificationTemplate.php`, `app/Models/WorkflowTacheEvenement.php`, `app/Services/Notifications/{NotificationDispatcher,NotificationInboxService,NotificationPreferenceService,DepenseNotificationService}.php`, `app/Services/Workflow/{WorkflowNotificationService,TacheWorkflowService,TacheActionService}.php`, `app/Services/WorkflowService.php`, `app/Services/Depense/OrdonnancementVersPaiementService.php`, `app/Http/Controllers/NotificationController.php`, `app/Http/Controllers/Api/V1/NotificationsApiController.php`, `app/Console/Commands/WorkflowRelancesCommand.php`, `config/notifications.php`, `bootstrap/app.php`, `routes/{web,api,channels}.php`, `resources/views/layouts/app.blade.php`, `resources/views/notifications/index.blade.php`, `resources/views/components/form/alerts.blade.php`, `frontend/src/{types.ts,layouts/AppLayout.vue,views/NotificationsView.vue}`, `database/seeders/NotificationsAlertesSeeder.php`, `database/scripts/empreintes_donnees_protegees.php`.

## 7. API

| Verbe | Endpoint (`/api/v1/notifications`) | Fonction | État |
|---|---|---|---|
| GET | `/` | Liste paginée, filtres serveur (`lu`, `nature`, `priorite`, `action`, `module`, `type`, `du`, `au`, `q`) | complété |
| GET | `/unread-count` | Compteur | existant |
| GET | `/summary` | Synthèse | **nouveau** |
| GET | `/{id}` | Détail (404 si autre destinataire) | **nouveau** |
| GET | `/{id}/destination` | Destination sûre, action disponible, message | **nouveau** |
| POST / PATCH | `/{id}/read` | Marquer lue | PATCH **nouveau** |
| POST / PATCH | `/{id}/unread` | Marquer non lue | **nouveau** |
| POST / PATCH | `/tout-lu`, `/read-all` | Tout marquer lu | PATCH **nouveau** |
| POST / PATCH | `/{id}/archiver`, `/{id}/archive` | Archiver | PATCH **nouveau** |
| GET / PUT | `/preferences` | Préférences | existant |

Web : `GET /notifications/apercu` (panneau rapide, JSON), `POST /notifications/{id}/non-lue`.

## 8. Intégrations métier

| Processus | Événements notifiés |
|---|---|
| EB PAP / hors PAP | Étape à valider (action), décision à l'initiateur (rejet, retour, validation finale), relances/rappels/escalade |
| ENG | Soumission automatique (ou échec : alerte), étapes Direction du Budget / CF, décisions, liquidation générée au visa |
| LIQ | Certification du service fait, visa CF, décisions |
| ORD | Circuit SG (≤ seuil) ou Président (> seuil), décision, **paiement à préparer pour l'Agence comptable** |
| PAY | Étapes comptable / agent comptable, paiement validé, **partiel avec solde** |
| Mes Tâches | Création → notification ; décision → action close et étape suivante notifiée ; réaffectation ; annulation → action annulée |

## 9. Problèmes résiduels et limitations

1. **Temps réel non vérifié de bout en bout** : Reverb est installé mais non démarré en local (`BROADCAST_CONNECTION=log`). La diffusion est testée au niveau de l'événement ; l'actualisation périodique (60 s) assure la reprise. Pour l'activer : `BROADCAST_CONNECTION=reverb`, variables `REVERB_*`, `php artisan reverb:start`.
2. **Tests frontend automatisés absents** : pas de Playwright dans le projet. Le SPA est contrôlé par `vue-tsc` (aucune erreur sur les fichiers modifiés ; 2 erreurs préexistantes dans `InteropDocsView.vue`) ; la cloche Blade est vérifiée par test HTTP de rendu, pas en navigateur.
3. **N7** : 14 notifications GED/PAP/budget/RBM hors dispatcher (sans nature, ni journal d'envoi) — inchangé, à traiter dans un lot dédié.
4. **N10** : téléphones et expéditeur e-mail à renseigner (exploitation).
5. **Indicateurs** : délais événement → livraison et création → première lecture non calculés.
6. **Paramétrage** : délais de rappel, obligatoires et cadence en configuration versionnée, sans écran d'administration ; la criticité par type reste prioritaire sur celle demandée par l'émetteur (règle existante, paramétrable en base).
7. **Exploitation** : rappels, relances et e-mails supposent `schedule:run` et `queue:work` en service (non actifs sur le poste local).
8. Les matrices de délais, priorités et escalades restent à valider par l'administration fonctionnelle (référentiel § 26).

## 10. Préservation des données protégées

Empreintes SHA-256 de 21 catégories (utilisateurs et mots de passe chiffrés, rôles, permissions, affectations, organigramme, nomenclature, Budget 2026, dossiers EB/ENG/LIQ/ORD/PAY, documents GED, journal d'audit) : **identiques avant et après** (`docs/audits/notifications/empreintes_avant.json`, `empreintes_apres.json`). Journal central : 3 071 écritures, id max 7 364, inchangé. Aucune commande destructive ; vérifications PostgreSQL faites en transactions annulées (compteurs identiques avant/après). Sauvegarde préalable : `pg_dump` des tables de notifications.
