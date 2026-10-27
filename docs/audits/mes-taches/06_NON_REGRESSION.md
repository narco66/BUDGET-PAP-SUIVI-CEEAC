# 06 — Rapport de non-régression du module « Mes Tâches »

*Date : 25/10/2026. Base de test isolée (SQLite en mémoire) ; la base PostgreSQL n'est jamais utilisée par la suite de tests.*

## 1. Référence

Avant toute modification, la suite complète a été exécutée pour établir la liste des échecs **préexistants** : 150 tests en échec (référence `base.txt`), dus à des chantiers antérieurs (exports, PDF, téléversements, écrans de workflow, sauvegardes, fixtures). Une régression s'entend donc comme un test **absent de cette référence** qui échoue après intervention.

## 2. Passes exécutées

| Passe | Périmètre | Résultat | Nouveaux échecs |
|---|---|---|---|
| Après les lots L2–L3 (moteur, service de décision) | 203 tests ciblés (workflow, chaîne de dépense, SoD, notifications) | 192 réussis, 11 en échec | **0** (les 11 figurent dans la référence) |
| Finale (après L1–L8), filtre `Workflow, Depense, Sod, Notification, Engagement, Besoin, MesTaches, AuditTrail, Liquidation, Ordonnancement, Paiement` | 275 tests | 260 réussis, 15 en échec | 1 apparent, analysé ci-dessous |
| `MesTachesTest` seul | 18 tests | 18 réussis (81 assertions) | 0 |

## 3. Analyse des 15 échecs de la passe finale

14 échecs figurent dans la référence préexistante : `BudgetNomenclatureAuditTest::test_engagement_refuse_imputation_hors_exercice`, `DepenseD4Test::test_export_ecritures_route`, `DepenseTest::test_pdf_engagement_se_genere`, `DepenseTest::test_upload_piece_justificative`, `ExerciceReferentielTest::test_workflow_ouverture_cloture`, `NotificationsAlertesP1Test::test_dashboard_alertes_et_resolution`, `PlanificationStrategiquePhaseP3Test::test_workflow_validation_plan`, `RbmPhase4Test::test_page_notifications`, `ReportingP2Test::test_workflow_valider_publier`, `SauvegardeTest::test_workflow_restauration_quatre_yeux`, `WorkflowP1Test::test_commande_relances_notifie_et_journalise`, `WorkflowP2Test::test_dashboard_workflow_accessible`, `AuditTrailHardeningTest::test_violation_de_separation_des_fonctions_est_journalisee`, `DepenseD5Test::test_parcours_e2e_http_besoin_vers_paiement`.

Le 15e, `FactoriesDemoGraphTest::test_factories_construisent_un_graphe_rbm_budget_depense_coherent`, n'est **pas imputable au module Mes Tâches** : il passe lorsqu'il est exécuté seul (1 test, 12 assertions). Son échec en série vient de l'épuisement du générateur `faker()->unique()`, partagé par tous les tests d'un même processus (« Maximum retries of 10000 reached »), et d'une modification du journal central apportée pendant cette passe par le chantier suivant (journalisation), corrigée depuis.

**Conclusion : aucune régression imputable au module « Mes Tâches ».**

## 4. Fonctionnalités existantes vérifiées comme conservées

- Ancienne corbeille `workflow/validations`, `WorkflowCorbeilleService`, `GET /api/v1/workflow/instances` : routes et tests intacts.
- Décisions métier depuis les écrans EB, ENG, LIQ, ORD, PAY (Blade et API) : mêmes routes, mêmes contrôles, désormais regroupés dans `DecisionMetierService`.
- Circuits PAP, Budget, Recettes, SE : tâches créées sans modification des définitions de circuit.
- Notifications : la notification d'étape ouvre la fiche de tâche ; la lecture ou la suppression d'une notification n'affecte pas la tâche (test dédié).
