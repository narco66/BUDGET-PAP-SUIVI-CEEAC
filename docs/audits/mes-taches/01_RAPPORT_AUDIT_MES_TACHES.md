# 01 — Rapport d'audit initial du module « Mes Tâches »

*Date : 25/10/2026. Référentiel : `Module_Mes_Taches_BUDGET_CEEAC_GESBUDEP.md` v1.0. Audit réalisé sans modification du code, du schéma ni des données.*

## 1. Cadre et précautions

| Élément | Constat |
|---|---|
| Dépôt | Git, branche `audit/chaine-depense-2026`, dernier commit `9fac440` ; 82 fichiers modifiés non commités (travaux antérieurs, dont migration PostgreSQL, chaîne de dépense, notifications) |
| Point de restauration | `stash@{0}` « Point de restauration avant Mes Tâches (25/10/2026) » créé par `git stash create` + `git stash store`, sans toucher au répertoire de travail ; fichiers non suivis archivés (`untracked_avant_mes_taches.tgz`) |
| Données protégées | Empreintes déterministes initiales : `empreintes_avant.json` (14 catégories : utilisateurs, rôles, permissions, affectations, organigramme, nomenclature, Budget 2026) |
| Maquettes Figma | **Aucune maquette présente dans `/docs/`** : conformité visuelle aux maquettes non vérifiable ; conventions de l'application retenues |
| Procédures | `docs/audit-chaine-depense/references/01_EB … 05_PAY` ; audit antérieur `docs/audit-workflow-validation-2026.md` |

## 2. Architecture analysée

| Couche | Réalité du projet | Écart avec la cible du référentiel (§ 14) |
|---|---|---|
| Frontend | Interface Blade + Bootstrap 5 + Alpine (principale) et SPA Vue 3 + Bootstrap (`frontend/`) en coexistence | Cible React + Tailwind + Font Awesome 7 : **non retenue** (règle « ne pas reconstruire » ; Bootstrap Icons partout). Écart documenté |
| Backend | Laravel 13, API REST v1 (Sanctum session) | Conforme |
| Base | PostgreSQL 18.4 | Conforme |
| Moteur de workflow | `WorkflowService` + `workflows` / `workflow_etapes` (définitions versionnées `workflow_versions`) / `workflow_instances` / `workflow_instance_etapes` ; transitions conditionnelles (`WorkflowTransitionResolver`), groupes parallèles, SoD (`SodPolicyService`), visas (`VisaElectroniqueService`), audit (`audit_workflow`) | Moteur existant **réutilisable** : ne pas créer de moteur concurrent |
| « Mes Tâches » existant | `WorkflowCorbeilleService::tachesPour()` ; page Blade `workflow/validations` ; API `GET /api/v1/workflow/instances` ; vue SPA `WorkflowCorbeilleView.vue` | Simple corbeille sans entité tâche |
| Délégations | `delegations` (structure, délégant, délégué, dates, motif, actif) et `interims` ; `WorkflowInstitutionResolver` | Pas de périmètre par module ou par acte, pas de justificatif |
| Notifications | `NotificationDispatcher`, `workflow.etape_en_attente` (corrigé le 25/10 : ciblage organique) | Pas de lien vers une tâche |

## 3. Fonctionnalités présentes

- Corbeille des étapes en attente agissables par l'utilisateur (Blade et SPA), libellé et lien vers le dossier, indicateur de retard (`echeance_le`).
- Décisions par dossier depuis les modules (EB, ENG, LIQ, ORD, PAY, PAP, Budget, Recettes, SE) : valider, rejeter (motif et catégorie), renvoyer (EB).
- Échéance par étape (`delai_jours`), relances SLA (`workflow:relances`), séparation des fonctions (initiateur, validation consécutive), délégations et intérims organiques, seuil d'ordonnancement paramétré et historisé, paiements partiels et reliquats.

## 4. Anomalies

| # | Anomalie | Preuve | Criticité |
|---|---|---|---|
| A1 | Pas d'entité « tâche » : la corbeille est recalculée à la volée ; aucune référence, aucun historique propre, pas de prise en charge, de priorité, de réaffectation, de suspension ni de commentaire | `WorkflowCorbeilleService` ; tables `workflow_*` | Critique |
| A2 | La corbeille charge **toutes** les instances en cours puis filtre en mémoire ; ni pagination, ni filtre, ni tri serveur | `WorkflowCorbeilleService::tachesPour()` | Majeure |
| A3 | **Aucun verrou en décision** : deux validateurs peuvent valider simultanément la même étape (double visa, double transition) | `WorkflowService::agir()` : `fresh()` sans `lockForUpdate` | Critique (RG-07, RG-14) |
| A4 | Le renvoi pour correction **réécrit** la ligne de l'étape précédente (acteur et date effacés) : l'historique de la tâche antérieure est perdu dans le modèle | `WorkflowService::apresRenvoi()` | Majeure |
| A5 | Pas de notification après un renvoi : l'acteur de l'étape précédente n'est pas prévenu | `apresRenvoi()` sans `notifierEtapeCourante()` | Majeure |
| A6 | `WorkflowPermissionsSeeder` jamais exécuté et absent de `DatabaseSeeder` : les permissions `workflow.*` n'existent pas en base. *Rectificatif après vérification* : elles sont résolues à l'exécution par `WorkflowPermissionAliases` (ex. `workflow.view` ← `audit.voir`), la corbeille n'est donc pas en 403 ; l'écart porte sur la traçabilité des droits (alias implicites plutôt que permissions explicites) | Requête sur `permissions` ; `app/Support/WorkflowPermissionAliases.php` ; tests `MesTachesTest` | Mineure (initialement classée Critique à tort) |
| A7 | `WorkflowPermissionsSeeder` n'attribue rien au Commissaire ni à l'Agent comptable, acteurs de circuits | Seeder | Majeure |
| A8 | L'administrateur peut agir sur toute étape (`peutAgir` : `hasRole('administrateur')`), contraire à « pas de pouvoir décisionnel métier implicite » (§ 6.1) | `WorkflowService::etapeTraitablePar()` | Majeure (à arbitrer) |
| A9 | Logique de décision **dupliquée** dans 6 contrôleurs (EB, ENG, étapes de dépense, en Blade et en API) avec des écarts | Contrôleurs `agir()` | Majeure |
| A10 | Le visa d'un engagement enregistre `controleur_id` dès la validation de la Direction du Budget (étape 1) | `EngagementController` / `EngagementApiController::agir()` | Mineure |
| A11 | Le visa est horodaté mais la notification part dans la transaction, avant validation | `demarrer()`, `apresValidation()` | Mineure |

## 5. Vulnérabilités

| # | Vulnérabilité | Preuve | Criticité |
|---|---|---|---|
| V1 | **Contournement du double contrôle du RIB** : la version Blade du visa des étapes (`EtapeDepenseController::agir`) n'appelle pas `RibControleService::assertValidationIndependante`, contrairement à l'API | Comparaison des deux contrôleurs | Critique |
| V2 | L'API générique `POST /api/v1/workflow/instances/{id}/decisions` appelle directement le moteur : elle contourne contrôle de version, idempotence, contrôle du RIB, enregistrement du valideur et notifications propres aux modules | `WorkflowApiController::decision()` | Critique |
| V3 | Double validation concurrente possible (A3) | — | Critique |

## 6. Risques et dépendances

- Le moteur est partagé par 14 circuits (PAP, BUDGET, BESOIN*, ENGAGEMENT, LIQUIDATION, ORDONNANCEMENT*, PAIEMENT, RECETTES, RAPPORT, STRATEGIE, COLLECTE) : toute modification doit être additive et couverte par les tests existants (`Workflow*Test`, `ChaineDepense*Test`, `SodInterRolesTest`).
- Les décisions passent par les contrôleurs métier qui portent policies, idempotence (`IdempotenceActionService`), version (`If-Match`) : le module ne doit pas les contourner.
- Coexistence Blade / SPA : la page utilisée au quotidien est Blade ; la SPA doit consommer la même API.
- Modification des droits interdite sans autorisation : le module doit fonctionner sans exécuter `WorkflowPermissionsSeeder`, dont l'exécution reste soumise à autorisation.
