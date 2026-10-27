# Audit du module Workflow — BUDGET-CEEAC / GESBUDEP

**Date :** 25 octobre 2026 · **Référentiel :** « Description fonctionnelle et technique détaillée du module Workflow » v1.0 (25/10/2026)
**Base :** PostgreSQL 18 (15 circuits, 39 étapes, 122 instances, 232 décisions d'étape, 213 visas, 336 événements d'audit workflow, 220 tâches).
**Audit antérieur :** `docs/audit-workflow-validation-2026.md` (plan W-P0 → W-P3, en grande partie réalisé depuis).
**Empreintes avant intervention :** `docs/audits/workflow/empreintes_avant.json` (données protégées) et `empreinte_historique_avant.json` (historique du workflow).

## 1. Architecture constatée

| Composant du référentiel | Existant | Appréciation |
|---|---|---|
| `WorkflowEngine` | `App\Services\WorkflowService` (demarrer, peutAgir, valider, rejeter, renvoyer, traiterDecision) : verrou d'instance, contrôle d'étape attendue, visas, audit, tâches, notifications | ✅ Socle solide |
| `WorkflowDefinitionService` | `WorkflowCircuitAdminService` + `WorkflowVersionService` + écrans `/admin/workflows` et API `api/v1/workflow/admin` | 🟡 Voir A1-A3 |
| `WorkflowAuthorizationService` | `peutAgir`, `WorkflowInstitutionResolver` (structure validatrice, délégation, intérim), `SodPolicyService` (initiateur ≠ valideur, pas deux validations consécutives, violations journalisées), `DirecteurBudgetHabilitationService` | ✅ |
| `WorkflowAssignmentResolver` | `WorkflowInstitutionResolver`, `WorkflowNotificationService::destinataires` | ✅ |
| `WorkflowTaskService` | `TacheWorkflowService`, `TacheActionService` (prise en charge, réaffectation motivée, suspension, verrou optimiste `version`) | ✅ |
| `WorkflowSlaService` | Échéance par étape (`delai_jours`), relances, rappels et escalade (module Notifications) | ✅ |
| `WorkflowDocumentGate` | Contrôles métier ponctuels (service fait certifié avant visa CF, RIB) ; pas de pièces obligatoires paramétrables par étape | 🟡 |
| `WorkflowEventPublisher` | Hooks `PilotableParWorkflow` (auWorkflowDemarre/Valide/Rejete/Renvoye), transitions EB→ENG→LIQ→ORD→PAY par services dédiés ; pas d'outbox | 🟡 |
| `WorkflowAuditService` | `audit_workflow`, `visas_electroniques` (empreinte), journal central (`journaux_audit`) | ✅ |
| Conditions (guards) | `WorkflowConditionEvaluator` : expressions JSON à opérateurs fermés, **aucun script exécuté** | ✅ (§ 18) |
| Branchements / parallélisme | `WorkflowTransitionResolver`, `groupe_parallele` | ✅ |
| Interfaces | Corbeille des validations, Mes Tâches, tableau de bord, audit, détail du dossier avec ligne du temps et circuit prévu | ✅ |

> **Écart de stack** : le référentiel cite React/Tailwind/Font Awesome ; l'application utilise Blade + Bootstrap 5 (+ SPA Vue 3). Conservé (aucune réécriture).

## 2. Circuits de la chaîne de dépense (§ 8)

| Circuit référentiel | Paramétrage existant | État |
|---|---|---|
| EB PAP : Directeur → Commissaire (département technique) ou SG (direction d'appui) | `BESOIN_PAP` (directeur → commissaire), `BESOIN_PAP_APPUI` (directeur → SG) | ✅ |
| EB hors PAP : DRHMG → SG → Président → engagement automatique | `BESOIN_HORS_PAP` (« Validation DRHMG » → SG → Président) ; `BesoinVersEngagementService` (lien unique : index unique `engagements.expression_besoin_id`) | ✅ |
| ENG : Direction du Budget → CF → LIQ automatique | `ENGAGEMENT` (directeur habilité Direction du Budget → CF), `EngagementVersLiquidationService` | ✅ |
| LIQ : service fait → CF → ORD automatique | `LIQUIDATION` (chef de service → CF), certification obligatoire avant visa CF | ✅ |
| ORD : seuil paramétrable 5 000 000, ≤ SG, > Président | `ORDONNANCEMENT` / `ORDONNANCEMENT_PRESIDENT`, `SeuilOrdonnancementService` (paramètre puis configuration, snapshot du seuil appliqué) | ✅ |
| PAY : comptable → agent comptable, partiels | `PAIEMENT`, quotas de paiement, partiels tracés | ✅ |

## 3. Anomalies

| # | Anomalie | Gravité | Preuve / référence |
|---|---|---|---|
| A1 | **Suppression d'une étape de circuit = effacement de l'historique** : la clé `workflow_instance_etapes.workflow_etape_id` est en `ON DELETE CASCADE` et l'administration supprime les étapes retirées (`syncEtapes`). Retirer une étape efface les décisions passées et en cours de tous les dossiers. | **Critique** | `pg_constraint` ; P07, § 11, interdiction « destruction de l'historique » |
| A2 | **Versionnement inopérant** : `workflow_versions` vide, aucune publication lors de la création ou de la modification d'un circuit ; **0/122 instance épinglée** à une version (`workflow_version_id` nul). La modification d'un circuit est immédiate, sans trace de la définition appliquée. | Majeur | P06, § 7, WF-020 |
| A3 | **Aucune validation de publication** : un circuit peut être enregistré avec des ordres en double, un rôle inexistant, des transitions vers une étape inconnue ou des délais négatifs. | Majeur | § 7.2 |
| A4 | **Double démarrage possible** : l'absence de circuit en cours est vérifiée hors transaction, sans verrou ni contrainte : deux soumissions simultanées créent deux instances actives. | Majeur | P04, WF-004 ; aucune contrainte d'unicité sur `workflow_instances` |
| A5 | **Libellé d'étape non conservé** sur l'instance : la ligne du temps lit la définition vivante ; un libellé modifié réécrit l'historique affiché. | Moyen | P05, § 17 (immutabilité) |
| A6 | **API de décision** : toute erreur renvoyée en 422 (pas de 409 en cas de conflit ni de 403 en cas d'habilitation refusée), pas de clé d'idempotence ni d'étape attendue, catégorie de rejet remplacée silencieusement par « technique », pas d'historique dans le détail d'instance, refus non tracés. | Moyen | § 18, P03, P04, WF-010 |
| A7 | Pas de pièces obligatoires paramétrables par étape (contrôles codés par module) | Moyen | § 14 |
| A8 | Pas d'outbox : les effets aval sont exécutés dans la transaction de décision (atomiques, donc sans transition fantôme), mais sans file de reprise dédiée | Faible | § 16.2 |
| A9 | Annulation d'instance : pas de commande générique (les règles de compensation après effet comptable restent à arbitrer, § 26) | Faible | § 6, § 11 |
| A10 | Gouvernance de publication (rédacteur / vérificateur / approbateur distincts) non outillée | Moyen | § 19 |

## 4. Points conformes conservés

Séparation des fonctions et journalisation des violations ; verrou d'instance et contrôle d'étape attendue (une seule décision par étape) ; motif obligatoire au rejet et au retour, catégorie de rejet ; visas électroniques avec empreinte ; audit workflow ; transitions aval idempotentes (index unique EB→ENG, brouillon réemployé) ; seuil d'ordonnancement paramétré et tracé ; délégations et intérims datés, revérifiés à chaque décision (`enCours` sur la date du jour) ; Mes Tâches et Notifications pilotés par l'instance (P08) ; conditions sans exécution de code ; tableau de bord (instances actives, retards, taux de rejet, délai moyen, visas).

## 5. Matrice de conformité initiale

| Réf. | Exigence | État | Écart → correction |
|---|---|---|---|
| P01 | Source unique de vérité | ✅ | — |
| P02 | Séparation des tâches | ✅ | — |
| P03 | Autorisation serveur | ✅ | API : statut 403 explicite (A6) |
| P04 | Idempotence | 🟡 | A4 (démarrage), A6 (clé d'idempotence API) |
| P05 | Traçabilité | 🟡 | A5, refus API non tracés (A6) |
| P06 | Versionnement | ❌ | A2 |
| P07 | Conservation | ❌ | A1 |
| P08 | Cohérence tâches / notifications | ✅ | — |
| P09 | Intégrité transactionnelle | ✅ | Effets aval dans la transaction |
| P10 | Paramétrabilité | 🟡 | Seuil, délais, rôles paramétrés ; pièces par étape non (A7) |
| § 7.2 | Validations de publication | ❌ | A3 |
| § 8 | Circuits chaîne de dépense | ✅ | — |
| § 10 | Délégation, SoD | ✅ | — |
| § 11 | Exceptions (retour, rejet, suspension, réaffectation, concurrence) | ✅/🟡 | Annulation générique (A9) |
| § 12 | Délais, rappels, escalade | ✅ | (module Notifications) |
| § 13 | Mes Tâches / Notifications | ✅ | — |
| § 14 | Pièces et preuves | 🟡 | A7 |
| § 15 | Écrans | ✅/🟡 | Comparaison de versions à ajouter (A2) |
| § 17 | Modèle de données, contraintes | 🟡 | A1, A4, A5 |
| § 18 | API et codes d'erreur | 🟡 | A6 |
| § 19 | Gouvernance de publication | 🟡 | A10 |
| § 21 | Indicateurs | ✅ | — |

## 6. Corrections retenues

1. **A1** : clé étrangère en `RESTRICT` ; l'administration refuse la suppression d'une étape déjà utilisée par des dossiers.
2. **A5** : libellé d'étape conservé sur l'instance (colonne `libelle`, rétro-alimentée) et affiché par la ligne du temps.
3. **A2** : publication d'une version (instantané) à chaque création/modification de circuit ; instance épinglée à la version courante au démarrage ; version initiale publiée pour les 15 circuits existants ; historique des versions consultable.
4. **A3** : validation bloquante avant publication.
5. **A4** : démarrage sous verrou du dossier + index unique partiel « une instance en cours par dossier ».
6. **A6** : API — 403 / 409 / 422 distincts, clé d'idempotence, étape attendue, catégorie de rejet obligatoire, historique (décisions, visas) dans le détail, refus tracés au journal central.
7. **A7 – A10** : documentés comme écarts résiduels (arbitrages institutionnels requis, § 26).
