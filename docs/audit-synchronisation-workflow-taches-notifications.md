# Synchronisation Workflow / Notifications / Mes Tâches — chaîne de dépense EB → ENG → LIQ → ORD → PAY

**Date :** 26 octobre 2026 · **Base :** PostgreSQL 18 (`gest_budget_pap`, port 5433) · **Application :** Laravel 13.15, écrans Blade + application Vue (corbeille `/workflow/corbeille`)

**Résultat :** toute action humaine attendue sur un dossier de dépense est désormais une tâche visible chez son titulaire dans « Mes Tâches », accompagnée d'une notification d'action ouvrant cette tâche. La tâche se clôt quand l'action est réalisée. Notifications, tâches et moteur appliquent une règle d'affectation unique. Les dernières tâches reçues apparaissent en tête, tous processus confondus.

---

## 1. État initial

Le module « Mes Tâches » (livré le 25/10) projetait les **étapes de circuit** du moteur de workflow (`workflow_taches`, une tâche par étape « en attente »). Le diagnostic en lecture seule (script de contrôle exécuté sur PostgreSQL, puis rejeu du parcours signalé dans une transaction annulée) a établi :

| Contrôle | Constat |
|---|---|
| Étapes de circuit en attente sans tâche | 0 sur 11 instances de dépense en cours |
| Tâches en double, tâches orphelines | 0 |
| Notifications d'action d'étape sans tâche | 0 (18 notifications, toutes adossées à une tâche ouverte) |
| **Actions humaines hors circuit sans tâche possible** | **27 dossiers** : EB préparées par un point focal, ENG à soumettre / en arbitrage / rejetés, LIQ et ORD en brouillon ou rejetés, PAY à préparer ou rejetés, reliquat à liquider |
| Tri par défaut de « Mes Tâches » | Échéance croissante : la tâche la plus récente arrive **en dernière position** de chaque file |
| Cloisonnement | Une EB de DIR-RHMG arrivait chez le directeur du Budget et chez celui de la Planification |

## 2. Anomalies constatées

| # | Processus / transition | Anomalie | Reproduite |
|---|---|---|---|
| A1 | EB : point focal → chef de service | Le chef de service ne reçoit ni notification ni tâche quand le point focal crée l'EB (cas 2.1) | Oui, sur PG (EB-2026-0040, rejeu annulé) |
| A2 | ENG / LIQ / ORD / PAY hors circuit | Engagement à soumettre ou en arbitrage, liquidation / ordonnancement en brouillon, paiement à préparer, dossiers rejetés à corriger, solde après paiement partiel : **aucune tâche** | Oui, 27 dossiers réels |
| A3 | EB : retour pour correction | Le dossier repart chez le **valideur précédent** (ou reste chez celui qui renvoie à l'étape 1). Le demandeur n'a aucune tâche de correction et la resoumission échoue (« Un circuit est déjà en cours sur cet objet. », réserve EB-01 de la démo) | Oui (code et démo du 24/10) |
| A4 | Toutes | Nouvelle tâche en fin de liste : au-delà de 20 tâches, elle passe en page 2 alors que la notification est reçue — explication la plus probable du cas 2.2 | Oui (position mesurée dans chaque file) |
| A5 | Étapes à niveau organique (chef de service, directeur, commissaire) | Les tâches suivent le **rôle seul** (tout directeur voit toute EB), les notifications le **responsable organique** : deux règles divergentes | Oui (code ; sans écart visible sur les données de démo, où un seul responsable est désigné sur 78 structures) |
| A6 | EB : chef de service → directeur | `EB-2026-0001` : notification du 24/10 à 16:59, tâche créée le 25/10 à 04:38 par la reprise au déploiement de « Mes Tâches » — symptôme 2.2 historique | Oui (données) ; régularisé le 25/10 |
| A7 | EB | Le point focal ne pouvait pas joindre de pièces à l'EB qu'il prépare (droit de modification exigé) | Oui (EB 189), corrigé |

## 3. Causes racines

1. **« Mes Tâches » ne connaissait que les étapes d'un circuit démarré** (`workflow_instance_id` obligatoire). Les actions qui précèdent ou suivent un circuit n'existaient au mieux que comme notification (« paiement à préparer »), et sinon nulle part : soumettre, corriger, préparer, solder.
2. **Deux règles de destinataires.** Les tâches suivaient le rôle (`TacheQueryService::candidates`), les notifications le responsable organique (`WorkflowNotificationService::destinataires`), et le moteur le rôle seul (`WorkflowService::etapeTraitablePar`).
3. **Sémantique du renvoi.** `WorkflowService::apresRenvoi` rouvrait l'étape N−1 chez un valideur pendant que l'EB passait « à corriger », et la soumission ne savait pas reprendre un circuit en cours.
4. **Tri par défaut** `echeance` / `asc` dans `TacheQueryService::lister`, repris par le formulaire Blade.

## 4. Matrice des transitions (après correction)

Légende : **T** = tâche dans « Mes Tâches » ; **N** = notification d'action ouvrant la tâche ; **i** = notification informative seulement. Les titulaires sont déterminés par la règle d'affectation unique (§ 5.1).

| Proc. | État initial | Action métier | État final | Titulaire (rôle) | Nature | Clôture de la tâche |
|---|---|---|---|---|---|---|
| EB | — | Création par un agent qui ne peut pas soumettre (point focal) | brouillon | chef de service de l'unité | T + N « EB à vérifier et soumettre » | Soumission (traitée) ou suppression (annulée) |
| EB | — | Création par un agent habilité à soumettre | brouillon | — | aucune (pas de tâche artificielle) | — |
| EB | brouillon / à corriger | Soumission → circuit BESOIN_PAP / _APPUI / _HORS_PAP | soumis | directeur (DRHMG pour le hors PAP) | T + N étape 1 ; i au demandeur | Décision de l'étape |
| EB PAP | soumis | Visa directeur | soumis | commissaire (BESOIN_PAP) ou SG (appui) | T + N | Décision |
| EB hors PAP | soumis | Visas DRHMG puis SG | soumis | SG puis Président | T + N | Décision |
| EB | soumis | **Retour pour correction** (toute étape) | à corriger | chef de service de l'unité (demandeur) | T + N « EB à corriger » ; aucune tâche de validation ; i au demandeur | Resoumission : le circuit **reprend à l'étape qui a renvoyé** |
| EB | soumis | Rejet (motif et catégorie) | rejeté | — | i au demandeur (décision finale) | Tâche de validation close |
| EB | soumis | Dernière validation | validé | — | i au demandeur ; ENG généré | — |
| ENG | — | Génération à la validation de l'EB, soumission automatique | soumis | directeur, structure DIR-BUDGET | T + N « Validation Direction du Budget » | Décision |
| ENG | — | Soumission automatique impossible | brouillon | gestionnaire budgétaire | T + N « ENG à soumettre » | Soumission |
| ENG | — | Crédits insuffisants | en attente d'arbitrage | gestionnaire budgétaire | T + N « reprendre après régularisation » | Nouvelle soumission |
| ENG | soumis | Visa Direction du Budget | soumis | contrôleur financier | T + N | Décision |
| ENG | soumis | Rejet | rejeté | gestionnaire budgétaire | T + N « ENG à corriger » | Nouvelle soumission |
| ENG | visé | Liquidation partielle (reliquat non liquidé) | visé | gestionnaire budgétaire | T + N « reliquat à liquider ou dégager » | Reliquat nul (liquidation ou dégagement) |
| LIQ | — | Génération au visa de l'ENG, circuit démarré automatiquement | soumis | chef de service (certification du service fait) | T + N | Décision |
| LIQ | brouillon | (automatisation désactivée) | brouillon | gestionnaire budgétaire | T + N « LIQ à compléter et soumettre » | Soumission |
| LIQ | soumis | Certification | soumis | contrôleur financier | T + N | Décision |
| LIQ | soumis | Rejet | rejeté | gestionnaire budgétaire (s'il reste un montant à liquider) | T + N « LIQ à corriger » | Nouvelle soumission |
| ORD | — | Génération à la validation de la LIQ | brouillon | gestionnaire budgétaire | T + N « ORD à soumettre » | Soumission |
| ORD | brouillon | Soumission, montant ≤ seuil paramétré (5 000 000 XAF) | soumis | Secrétaire général | T + N signature | Décision |
| ORD | brouillon | Soumission, montant > seuil | soumis | Président | T + N signature | Décision |
| ORD | soumis | Rejet | rejeté | gestionnaire budgétaire | T + N « ORD à corriger » | Nouvelle soumission |
| PAY | — | Émission de l'OP, paiement généré | brouillon | comptable | T + N « paiement à préparer » (notification métier rattachée, sans doublon) | Soumission |
| PAY | brouillon | Soumission | soumis | comptable (contrôles comptables) | T + N | Décision |
| PAY | soumis | Contrôle comptable | soumis | agent comptable (autorisation) | T + N | Décision |
| PAY | soumis | Rejet | rejeté | comptable (s'il reste à payer) | T + N « paiement à corriger » | Nouvelle soumission ou solde atteint |
| PAY | soumis | Autorisation, règlement **partiel** | validé | comptable | T + N « solde restant à payer » sur l'ORD ; chaîne **non clôturée** | Nouveau paiement couvrant le reliquat |
| PAY | soumis | Autorisation, règlement intégral | validé | — | i ; écritures ; chaîne clôturée | — |

Pour chaque tâche, l'application enregistre :
- **à la création** : la date de réception (`created_at`, base du tri), l'échéance (délai de l'étape, ou `workflow.taches.delai_preparation_jours`, 3 jours par défaut, pour les actions hors circuit) et l'événement « Tâche créée » ;
- **à la clôture** : la décision (validée, rejetée, retournée, ou action réalisée), son auteur, et la clôture des notifications liées chez tous leurs destinataires.

## 5. Corrections effectuées

### 5.1 Règle d'affectation unique — `AffectationTacheService`

Une seule fonction détermine qui doit agir. Elle est utilisée par la notification d'étape, l'éligibilité dans « Mes Tâches », l'autorisation du moteur (`etapeTraitablePar`) et les tâches de préparation :

1. **Rôle de niveau organique** (chef de service, directeur, commissaire, SG, Président) :
   - le responsable de la structure validatrice et ses suppléants (délégation, intérim) ;
   - à défaut, les porteurs du rôle affectés à cette structure ou à ses sous-structures.
2. **Rôle transversal** (contrôleur financier, gestionnaire budgétaire, comptable, agent comptable) : ses porteurs.
3. **Aucun titulaire organique trouvé** :
   - tous les porteurs du rôle reçoivent la tâche, qui est marquée `affectation_defaut` ;
   - un badge s'affiche dans la liste et un avertissement sur la fiche ;
   - un encart « Anomalies d'affectation » apparaît en supervision. L'affectation n'est jamais silencieuse.

La séparation des fonctions (initiateur, étape précédente, visa Direction du Budget) filtre ensuite les destinataires. La personne notifiée est donc exactement celle qui voit la tâche et peut la traiter. L'administrateur conserve son pouvoir d'agir sans figurer dans les files ; sa suppression reste soumise à autorisation.

### 5.2 Tâches de préparation — `TachePreparationService`

- Nouvelle nature de tâche `preparation` (`action_code` : `eb_soumettre`, `eb_corriger`, `eng_soumettre`, `eng_arbitrage`, `eng_corriger`, `liq_reliquat`, `liq_soumettre`, `liq_corriger`, `ord_soumettre`, `ord_corriger`, `pay_preparer`, `pay_corriger`, `pay_solde`).
- L'action attendue est **déduite de l'état réel du dossier** (statut, circuit en cours, reste à liquider ou à payer calculé par `DepenseQuotaService`), jamais d'une donnée figée.
- La synchronisation est déclenchée par les événements `saved` / `deleted` des cinq modèles. Elle s'exécute **après validation de la transaction** métier, est idempotente (index unique partiel) et ne bloque jamais l'opération : un échec est journalisé, et la commande la rejoue.
- La notification de la tâche est envoyée **après** la création de la tâche, dans un point de sauvegarde : la tâche ne dépend jamais de la réussite de la notification. Une notification d'action métier déjà ouverte sur le dossier (« paiement à préparer », exigée par le référentiel des notifications) est **rattachée** à la tâche au lieu d'être doublée.
- Dans « Mes Tâches », une tâche de préparation propose « **Traiter dans le dossier** ». Une décision tentée depuis « Mes Tâches » est refusée ; la tâche se clôt d'elle-même quand le dossier avance.

### 5.3 Retour pour correction de l'EB — contrat `RenvoiAuDemandeur`

- Un renvoi garde le circuit sur l'étape qui renvoie, et aucune tâche de validation n'existe pendant la correction.
- L'EB passe « à corriger » et la tâche `eb_corriger` naît chez le chef de service de l'unité.
- À la resoumission (`BesoinApiController::soumettre` et `ExpressionBesoinController::soumettre`), `WorkflowService::reprendreApresCorrection` reprend le circuit au même niveau. Cette reprise est journalisée sous `workflow_repris`, avec échéance, tâche et notification. L'instantané PAP est refigé sur les deux points d'entrée ; l'écran Blade ne le faisait pas jusqu'ici.
- Les autres circuits (PAP, Budget, Recettes…) conservent le renvoi à l'étape précédente.

### 5.4 Affichage de « Mes Tâches »

- **Tri par défaut** : réception décroissante, avec l'identifiant comme second critère stable. Le tri est appliqué côté serveur avant la pagination, et le formulaire Blade affiche le même défaut.
- **Colonnes** : action attendue (étape ou action à réaliser dans le dossier), initiateur du dossier, badge d'affectation par défaut.
- **Filtres** : ajout du filtre « Décision » (validées, rejetées, retournées, actions réalisées). Il complète les filtres existants : vue, statut, échéance (dont en retard), module EB, ENG, LIQ, ORD ou PAY, période, unité, priorité, recherche par référence.
- **API** (`WorkflowTacheResource`) : `nature`, `action_code`, `action_attendue`, `affectation_defaut`, `initiateur`, `dossier.cree_le`. La corbeille Vue les affiche.
- **Historique de la fiche** : l'historique d'une tâche hors circuit se limite au même dossier (`WorkflowTache::tachesLiees`). Sans ce garde-fou, la requête `where('workflow_instance_id', null)` aurait listé toutes les tâches de préparation.

### 5.5 Pièces justificatives de l'EB (A7)

Nouvelle capacité `ExpressionBesoinPolicy::joindrePieces` : le **demandeur** joint et retire ses propres pièces tant que l'EB reste modifiable, sans pouvoir la modifier ni la soumettre. Elle est appliquée à l'écran (`PieceController`) et à l'API (`PieceApiController`).

## 6. Fichiers modifiés ou créés

| Fichier | Nature |
|---|---|
| `app/Services/Workflow/AffectationTacheService.php` | **créé** — règle d'affectation unique |
| `app/Services/Workflow/TachePreparationService.php` | **créé** — tâches hors circuit |
| `app/Contracts/RenvoiAuDemandeur.php` | **créé** |
| `app/Console/Commands/TachesSynchroniserPreparationCommand.php` | **créé** — régularisation (`--dry-run`) |
| `app/Services/WorkflowService.php` | autorisation alignée, renvoi au demandeur, `reprendreApresCorrection` |
| `app/Services/Workflow/WorkflowNotificationService.php` | destinataires = règle unique + séparation des fonctions |
| `app/Services/Workflow/TacheWorkflowService.php` | pas de validation pendant la correction ; indicateur d'affectation par défaut |
| `app/Services/Workflow/TacheQueryService.php` | éligibilité des deux natures, tri par réception, filtre décision, initiateur, anomalies d'affectation en supervision |
| `app/Services/Workflow/TacheActionService.php` | action `traiter_dossier`, refus de décision sur une tâche de préparation |
| `app/Services/Institution/WorkflowInstitutionResolver.php` | `rolesOrganiques()` |
| `app/Models/WorkflowTache.php`, `app/Models/ExpressionBesoin.php` | nature, `tachesLiees()` ; contrat de renvoi |
| `app/Http/Controllers/TacheController.php`, `Api/V1/TachesApiController.php`, `app/Http/Resources/WorkflowTacheResource.php` | historique, filtres, champs exposés |
| `app/Http/Controllers/Api/V1/BesoinApiController.php`, `ExpressionBesoinController.php` | reprise après correction |
| `app/Policies/ExpressionBesoinPolicy.php`, `PieceController.php`, `Api/V1/PieceApiController.php` | pièces jointes par le demandeur |
| `app/Providers/AppServiceProvider.php` | abonnement des cinq modèles à la synchronisation |
| `config/workflow.php` | `taches.delai_preparation_jours` |
| `resources/views/taches/index.blade.php`, `show.blade.php`, `supervision.blade.php` | affichage |
| `frontend/src/types.ts`, `frontend/src/views/WorkflowCorbeilleView.vue` | affichage Vue |
| `tests/Feature/MesTachesSynchronisationTest.php` (créé), `MesTachesTest.php`, `PieceJustificativeBesoinTest.php` | tests |
| `tests/Recette/*`, `phpunit-recette-pg.xml` | **créés** — recette PostgreSQL en transaction annulée |

## 7. Migration

`2026_10_26_100000_add_taches_preparation`, additive :
- `workflow_instance_id` et `workflow_instance_etape_id` deviennent nullables ;
- ajout des colonnes `nature` (défaut `validation`), `action_code` et `affectation_defaut` ;
- ajout de l'index `(statut, created_at)` et de l'index unique partiel « une tâche de préparation ouverte par dossier et par action » ;
- recréation explicite de l'index partiel « une tâche ouverte par étape ».

Elle a été appliquée sur PostgreSQL le 26/10 après sauvegarde `pg_dump` des tables `workflow_taches`, `workflow_tache_evenements` et `notifications`. Les 227 tâches existantes sont intactes (nature `validation`). Le retour arrière supprime les tâches de préparation avant de rétablir les contraintes NOT NULL.

## 8. Régularisation des données

`php artisan taches:synchroniser-preparation`, exécutée d'abord en simulation (`--dry-run`) puis pour de bon :

- **23 tâches créées** :
  - 7 ENG : 3 à soumettre, 1 en arbitrage, 2 à corriger, 1 reliquat ;
  - 4 LIQ : 2 à soumettre, 2 à corriger ;
  - 6 ORD : 5 à soumettre, 1 à corriger ;
  - 6 PAY : 4 à préparer, 2 à corriger.
- Chaque tâche porte son événement de création ; les notifications ont été émises.
- **4 paiements rejetés** déjà remplacés par un paiement soldant l'ordonnancement n'ont volontairement **pas** reçu de tâche, la règle ayant été affinée après la première simulation.
- Une seconde simulation donne **0 écart** : la commande est idempotente.
- Aucun dossier, aucune instance, aucune étape, aucun droit ni aucune donnée institutionnelle n'a été modifié ou supprimé.

## 9. Tests exécutés

| Suite | Résultat |
|---|---|
| `MesTachesSynchronisationTest`, 16 tests (nouveau) : EB préparée, absence de tâche artificielle, soumission, renvoi puis reprise au bon niveau, ORD, PAY sans doublon, paiement partiel puis solde, paiement intégral, LIQ rejetée, ENG en arbitrage, refus de décision, idempotence, tri, cloisonnement, affectation par défaut, notification ↔ tâche | **16/16** |
| `MesTachesTest` (renvoi mis à jour au nouveau comportement), `WorkflowDepenseTest`, `NotificationsConformiteTest` | **62/62** avec la suite précédente |
| `PieceJustificativeBesoinTest` | **8/8** (3 nouveaux) |
| **Recette PostgreSQL de bout en bout** `ChaineDepenseTachesE2ETest`, transaction annulée, comptes et données réels (détail ci-dessous) | **4/4, 160 vérifications** |
| Non-régression élargie (Workflow, Notifications, Mes Tâches, Dépense, RBM, Cockpit, Gouvernance : 438 tests) | 402 réussis. Les 34 échecs et 2 erreurs sont tous antérieurs à l'intervention (détail ci-dessous) |
| Types Vue (`vue-tsc -b`) | Aucune erreur sur les fichiers modifiés ; 2 erreurs préexistantes dans `InteropDocsView.vue` |

**Scénarios couverts par la recette PostgreSQL :**

| Scénario | Contenu |
|---|---|
| A | EB du point focal, soumission, visa |
| F (EB) | Renvoi, correction, retour au même niveau |
| B | ENG, Direction du Budget puis contrôleur financier |
| C | Certification puis visa |
| D | ORD ≤ seuil vers le SG, ORD > seuil vers le Président |
| E / F (PAY) | Rejet, paiement partiel, solde |
| G | Ordre de réception |

À chaque étape, la recette vérifie que la tâche figure dans « Mes Tâches » de chaque titulaire, que chacun a une notification d'action qui la référence, que la fiche s'ouvre (HTTP 200), et que personne d'autre ne voit la tâche.

**Détail des échecs de la non-régression élargie**, tous antérieurs à l'intervention :
- **Redirection vers `/mfa`** (fichiers de test qui ne désactivent pas la MFA) : familles RBM, Gouvernance, D4, NotificationsAlertesP1.
- **Échecs déjà consignés** dans le rapport de non-régression du 25/10 : `DepenseD5Test::test_parcours_e2e…`, `WorkflowP1Test::test_commande_relances…`.
- **Publication de version de circuit** (`WorkflowP3Test`) : fonction non touchée par cette intervention.

**Preuve en conditions réelles.** L'EB 189 (`EB-2026-0040`), saisie par le point focal pendant l'intervention, a suivi exactement le parcours attendu :

| Heure | Événement |
|---|---|
| 18:56 | Tâche « à soumettre » pour les chefs de service et notification |
| 19:08 | Soumission : tâche « traitée » et notification close ; tâche et notification du directeur |
| 19:09 | Tâche du SG |

**Contrôles d'écran.** Playwright n'est plus installé (outil isolé de la session du 25/10). Les écrans ont été contrôlés côté serveur :
- rendu de `/mes-taches` et des fiches de tâche pour chaque titulaire ;
- présence de la tâche dans la page ;
- HTTP 200.

Les parcours d'écriture n'ont pas été joués au navigateur : ils créeraient des dossiers persistants dans la base de travail.

## 10. Anomalies résiduelles et recommandations

| # | Sujet | Recommandation |
|---|---|---|
| R1 | **Référentiel organique incomplet** (un seul responsable désigné sur 78 structures) : la plupart des tâches organiques sont en affectation par défaut | Désigner les responsables des directions, services et départements (ou y affecter les titulaires des rôles) : le cloisonnement s'applique alors automatiquement |
| R2 | Circuit EB : toute structure descendant de SG ou PRES est traitée comme structure d'appui (`BESOIN_PAP_APPUI` au lieu de `BESOIN_PAP`), anomalie NOM-02 de la démo | Arbitrer la règle `BesoinCircuitService` (hors périmètre de cette intervention) |
| R3 | Pas de rappel ni d'escalade pour les tâches de préparation (`NotificationRappelService` ne traite que les étapes) | Étendre les rappels SLA aux tâches de préparation |
| R4 | « Mes Tâches » filtre l'éligibilité en PHP après chargement des tâches ouvertes candidates (par rôle) : correct et trié avant pagination, mais coûteux au-delà de quelques milliers de tâches ouvertes | Pré-filtrer en SQL par unité autorisée, ou matérialiser les titulaires par tâche |
| R5 | Paiement automatique sans RIB non complétable, API qui crée un nouveau paiement (réserve NOM-01) ; écran Blade de création LIQ / ORD / PAY en erreur (`EtapeDepenseController::create`, argument manquant) | Corrections à planifier (consignées dans le jeu d'essai du 26/10) |
| R6 | Suites de tests qui ne désactivent pas la MFA (redirections `/mfa`) | Ajouter `config(['mfa.enabled' => false])` dans leur `setUp`, comme les autres suites |
| R7 | Rafraîchissement : les listes se mettent à jour au chargement ou à l'actualisation de la page (aucune reconnexion nécessaire) ; pas de diffusion temps réel de « Mes Tâches » | Optionnel : diffuser l'événement de création de tâche sur le canal de notifications existant |

**Rejeu.**

```
php vendor/bin/phpunit -c phpunit-recette-pg.xml
```

Recette PostgreSQL de bout en bout, en transaction annulée : aucune donnée persistée.

```
php artisan taches:synchroniser-preparation --dry-run
```

Contrôle des écarts entre dossiers et tâches de préparation, sans aucune écriture.
