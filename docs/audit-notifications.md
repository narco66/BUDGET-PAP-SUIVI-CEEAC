# Audit du module Notifications et alertes

*Date : 2026-10-25. Base auditée : PostgreSQL 18 `gest_budget_pap` (données rechargées depuis MySQL et dossiers de démonstration de la chaîne de dépense). Méthode : revue du code, requêtes sur les données, essais HTTP réels avec les comptes de démonstration.*

## Synthèse

Le module est bien conçu sur le papier : un point d'entrée unique (`NotificationDispatcher`), trois canaux (application, e-mail, SMS), des préférences par utilisateur, des modèles éditables, un journal d'envoi, des relances et une escalade par niveaux. **En l'état, il ne remplit pas sa fonction** :

- les utilisateurs voient la cloche et son compteur, mais **ne peuvent pas ouvrir leurs notifications** (erreur 403) ;
- **aucun e-mail ni SMS n'a jamais été émis** ;
- les destinataires sont choisis **par rôle, sans tenir compte de la structure**, ce qui noie les acteurs sous des notifications sur lesquelles ils ne peuvent pas agir ;
- le journal d'envoi est **incohérent** avec les notifications reçues.

La cause principale est un défaut d'installation (permissions et référentiel jamais chargés), aggravé par trois défauts de code. Les tests automatisés (36 tests, tous verts) ne le détectent pas : chaque test recrée lui-même permissions et référentiel.

| # | Constat | Gravité |
|---|---|---|
| N1 | Permissions `notifications.*` absentes : boîte de réception et préférences en 403 pour tous sauf l'administrateur | **Bloquant** |
| N2 | Référentiel vide (types, canaux, modèles) : e-mail et SMS jamais émis | **Bloquant** |
| N3 | Corrélation notification ↔ journal erronée : 91 doublons, 54 types incohérents, 112 notifications sans type | Majeur |
| N4 | Destinataires = tous les titulaires du rôle, sans structure, délégation ni séparation des fonctions | Majeur |
| N5 | Notifications de la chaîne de dépense en double et adressées à un ancien circuit | Majeur |
| N6 | Aucun traitement de file ni planificateur : 1 135 tâches bloquées, relances et escalades jamais exécutées | Majeur (exploitation) |
| N7 | 14 notifications métier contournent le dispatcher (ni journal, ni préférences, ni e-mail) | Moyen |
| N8 | Alertes critiques désactivables par l'utilisateur | Moyen |
| N9 | Envois externes (e-mail, SMS) déclenchés à l'intérieur d'une transaction | Moyen |
| N10 | Données de contact absentes : aucun téléphone, expéditeur `hello@example.com` | Moyen |
| N11 | Code mort et hygiène (`envoyerSiAutorise`, `WorkflowCorbeilleNotification`, fins de ligne) | Faible |

## Constats détaillés

### N1 — Boîte de réception inaccessible (bloquant)

Le code exige `notifications.view` (consulter), `notifications.manage` (archiver, préférences), `notifications.send` (émettre) et `notifications.admin` (modèles). **Aucune de ces permissions n'existe en base.** Le seeder `NotificationsAlertesPermissionsSeeder` existe, mais `DatabaseSeeder` ne l'appelle pas.

Essais HTTP réels :

| Compte | Cloche affichée | `/notifications` | `/profil/notifications` |
|---|---|---|---|
| directeur | oui (compteur) | **403** | **403** |
| gestionnaire_budgetaire | oui (compteur) | **403** | **403** |
| administrateur | oui | 200 (accès par `Gate::before`) | 200 |

Le groupe de menu « Notifications et alertes » est également masqué pour tous les profils non administrateurs.

### N2 — E-mail et SMS jamais émis (bloquant)

Les tables `notification_types`, `notification_channels`, `notification_templates`, `email_templates` et `sms_templates` sont **vides** (seeder `NotificationsAlertesSeeder` non exécuté). Pour un type inconnu, `NotificationPreferenceService::canauxType()` ne retient que le canal `database`, même quand l'appelant demande `['database', 'email']`.

Mesures : `email_logs` = 0, `sms_logs` = 0, `notification_logs` = 457, tous en canal `database`. Les cinq types effectivement émis (`workflow.etape_en_attente`, `depense.besoin_soumis`, `depense.engagement_soumis`, `depense.paiement_valide`, `reporting.rapport_disponible`) n'ont aucune définition.

### N3 — Journal d'envoi incohérent (majeur)

Après `Notification::send()`, le dispatcher retrouve la notification créée par `$user->notifications()->latest()->value('id')`. Les identifiants sont des UUID non ordonnés, et plusieurs notifications d'un même utilisateur partagent la même seconde : la requête renvoie souvent une notification plus ancienne. Le dispatcher **réécrit alors le type et le module de cette ancienne notification**, et laisse la nouvelle sans type.

| Mesure | Valeur |
|---|---|
| `laravel_notification_id` présents plusieurs fois dans `notification_logs` | 91 |
| Entrées du journal pointant vers une notification d'un autre type | 54 |
| Notifications sans `type_code` (invisibles aux filtres par type et par module) | 112 / 457 |

**Correction :** générer l'UUID avant l'envoi (propriété `id` de la notification) ou écrire la ligne `notifications` directement, puis journaliser avec cet identifiant.

### N4 — Destinataires trop larges (majeur)

`WorkflowNotificationService::utilisateursParRole()` notifie **tous les utilisateurs actifs** ayant le rôle de l'étape :

- le Directeur de la Planification reçoit les visas « Direction du Budget », qu'il n'a pas le droit d'exercer (62 notifications) ;
- un délégataire de signature (délégation écrite du SG) **n'est jamais prévenu**, puisqu'il ne porte pas le rôle ;
- l'initiateur d'un dossier qui porte aussi le rôle de l'étape est notifié pour un visa qu'il ne peut pas donner (séparation des fonctions).

L'escalade des alertes (`AlerteEscaladeService`) suit la même logique : tous les directeurs et chefs de service sont notifiés au niveau 1, quelle que soit la structure concernée.

**Correction :** déterminer les destinataires avec la même règle que l'habilitation à agir (`WorkflowService::peutAgir` : rôle, structure, délégation, intérim, exclusion de l'initiateur).

### N5 — Doublons et ancien circuit dans la chaîne de dépense (majeur)

À la soumission d'une EB, deux notifications partent :

1. `workflow.etape_en_attente`, aux titulaires du rôle de l'étape courante (Directeur) ;
2. `depense.besoin_soumis` (`DepenseNotificationService::notifierBesoinSoumis`), au **Contrôleur financier et au gestionnaire budgétaire**, acteurs de l'ancien circuit à trois visas. Le circuit actuel est Directeur → SG ou Commissaire.

Résultat : le Contrôleur financier cumule 131 notifications, toutes non lues. Sur les 457 notifications en base, aucune n'a été lue.

**Correction :** supprimer les notifications « soumis » propres à la dépense lorsque le workflow notifie déjà l'étape, ou les réserver au demandeur (accusé de soumission).

### N6 — Files et planificateur non exploités (majeur, exploitation)

- `QUEUE_CONNECTION=database`, mais **aucun processus `queue:work`** ne tourne. La table `jobs` contient **1 135 tâches depuis le 16 juin**, toutes des diffusions temps réel `CockpitMetricsUpdated`, mises en file alors que la diffusion est désactivée (`BROADCAST_CONNECTION=log`).
- Les e-mails (`SendInstitutionEmailJob`) et les 10 notifications GED (`ShouldQueue`) resteraient bloqués de la même façon.
- **Aucune tâche planifiée Windows** n'exécute `php artisan schedule:run` : `workflow:relances`, `relances:metier`, `alertes:detecter-budget`, `alertes:escalader`, `notifications:retry-email` et `notifications:purge-archives` ne s'exécutent jamais. La table `alertes` est vide.

**Correction :** en exploitation, un service `queue:work` supervisé et une tâche planifiée chaque minute pour `schedule:run`. `CockpitMetricsUpdated` ne doit être émis que si une diffusion est configurée (ou passer en `ShouldBroadcastNow`).

### N7 — Notifications hors dispatcher (moyen)

14 classes envoient directement par `notify()` : GED (10, en file), PAP, budget et RBM (4). Elles ne passent ni par le journal d'envoi, ni par les préférences, ni par les canaux e-mail et SMS, ni par l'enrichissement type, module et criticité utilisé par les filtres de la boîte de réception.

### N8 — Alertes critiques désactivables (moyen)

`NotificationPreferenceService::canauxEffectifs()` renvoie une liste vide dès que l'utilisateur coupe toutes ses notifications (`actif = false`), **y compris pour les criticités `critique` et `urgence`**. À l'inverse, un utilisateur qui coupe le canal « application » le reçoit quand même si c'est le seul canal retenu : la préférence est ignorée sans le lui dire.

**Correction :** imposer au moins le canal « application » pour les criticités critique et urgence, et l'indiquer dans l'écran des préférences.

### N9 — Envois externes dans une transaction (moyen)

`NotificationDispatcher::envoyer()` crée les journaux et déclenche les envois e-mail et SMS à l'intérieur de `DB::transaction()` :

- avec un pilote de file autre que `database` (Redis), la tâche e-mail peut s'exécuter avant la validation de la transaction, ne pas trouver son `EmailLog`, et l'e-mail est perdu sans trace ;
- un SMS (appel HTTP à Twilio) envoyé dans la transaction ne peut pas être annulé si la transaction échoue ensuite, et allonge la durée des verrous.

**Correction :** déclencher e-mail et SMS après validation (`DB::afterCommit` ou `->afterCommit()` sur la tâche).

### N10 — Données de contact (moyen)

- Aucun des 17 comptes n'a de numéro de téléphone : le canal SMS ne peut rien acheminer.
- L'expéditeur est `hello@example.com` (`MAIL_FROM_ADDRESS`), et le pilote de messagerie est `log` : acceptable en développement, à remplacer avant toute recette e-mail.

### N11 — Hygiène (faible)

- `NotificationDispatcher::envoyerSiAutorise()` n'est appelée nulle part. De plus, elle avale toute `RuntimeException`, ce qui masquerait des échecs réels.
- `WorkflowCorbeilleNotification` n'est jamais utilisée.
- Une notification adressée à un seul `User` contourne le filtre « compte actif » de `normaliserDestinataires()`.
- Les fichiers du module sont en fins de ligne CRLF, contrairement au reste du code.

## Points conformes

- Les actions de la boîte de réception (lire, archiver, tout marquer lu) sont limitées aux notifications de l'utilisateur connecté (`$user->notifications()`) : pas d'accès aux notifications d'autrui.
- Le corps des e-mails sans modèle est échappé (`nl2br(e(...))`), et les modèles utilisent `appliquerCorpsSecurise`.
- Les échecs d'e-mail sont journalisés et relancés (3 tentatives, commande `notifications:retry-email`).
- Une panne de notification n'annule pas une décision financière : la chaîne de dépense passe par `envoyerSansAnnuler` et journalise `echec_reprise`.
- Chaque envoi est tracé dans `audit_notifications` (457 entrées, cohérentes avec le journal).

## État des corrections (2026-10-25)

| Constat | État | Réalisation |
|---|---|---|
| N1, N2 | ✅ Corrigé | Seeders exécutés et ajoutés à `DatabaseSeeder` ; permissions étendues à `agent_comptable`, `vice_president`, `lecteur`. Boîte de réception et préférences en 200 pour tous les profils testés ; e-mail vérifié de bout en bout (journal → envoi). |
| N3 | ✅ Corrigé | Identifiant fixé avant l'envoi (`notifyNow`) ; migration `2026_10_25_000001` : 0 notification sans type, 0 doublon, 0 type incohérent, 457 journaux ré-associés. |
| N4 | ✅ Corrigé | `WorkflowNotificationService::destinataires()` : responsable de la structure validatrice + délégataires et intérimaires, sinon titulaires du rôle filtrés (contrôle Direction du Budget) ; initiateur exclu. |
| N5 | ✅ Corrigé | Notifications de la chaîne de dépense recentrées sur le demandeur (accusés EB et ENG, avis de paiement) ; valideurs prévenus par le seul workflow, désormais aussi par e-mail. |
| N6 | ✅ Corrigé (poste) / à mettre en service (exploitation) | 1 135 diffusions purgées ; `CockpitMetricsUpdated::broadcastWhen()` empêche leur accumulation ; procédure `queue:work` / `schedule:run` documentée (`installation-locale.md`, `procedures-deploiement.md`). |
| N9 | ✅ Corrigé | E-mail et SMS déclenchés par `DB::afterCommit`. |
| N11 | ✅ Partiel | `envoyerSiAutorise` supprimée ; destinataire unique inactif écarté. |
| Liens « Voir » | ✅ Corrigé | Constat complémentaire : 442 liens pointaient vers `http://localhost` (APP_URL). Liens stockés en relatif, rendus absolus dans les e-mails ; migration `2026_10_25_000002` ; `APP_URL=http://budget-pap-suivi-ceeac.test`. |
| N7, N8, N10 | ⏳ Ouvert | Notifications GED/PAP/budget/RBM hors dispatcher ; alertes critiques désactivables ; téléphones et expéditeur à renseigner. |

## Plan d'action

| Priorité | Action | Effort |
|---|---|---|
| 1 | Exécuter `NotificationsAlertesPermissionsSeeder` et `NotificationsAlertesSeeder` sur la base, et les ajouter à `DatabaseSeeder` (N1, N2) | Faible |
| 2 | Corriger la corrélation notification ↔ journal et réaffecter les types des 112 notifications orphelines (N3) | Faible |
| 3 | Aligner les destinataires sur l'habilitation réelle à agir (structure, délégation, intérim, séparation des fonctions) (N4) | Moyen |
| 4 | Supprimer les doublons de la chaîne de dépense et corriger les destinataires de « Besoin soumis » (N5) | Faible |
| 5 | Mettre en place `queue:work` et `schedule:run` ; purger les 1 135 diffusions inutiles et conditionner `CockpitMetricsUpdated` (N6) | Faible |
| 6 | Faire passer les 14 notifications métier par le dispatcher (N7) | Moyen |
| 7 | Rendre les alertes critiques non désactivables ; envois externes après validation de la transaction (N8, N9) | Faible |
| 8 | Renseigner téléphones et expéditeur institutionnel avant la recette e-mail et SMS (N10) | Faible (métier) |
| 9 | Ajouter un test qui vérifie, sur une base installée par `DatabaseSeeder`, l'existence des permissions et du référentiel (prévient le retour de N1 et N2) | Faible |
