# 03 — Plan de mise en conformité

Principe : **compléter le moteur existant**, sans moteur concurrent ni reconstruction de l'interface. Les décisions restent exécutées par le moteur `WorkflowService`, avec les mêmes contrôles et effets métier, quel que soit le point d'entrée.

## Choix d'architecture

| Sujet | Décision | Justification |
|---|---|---|
| Entité tâche | Nouvelle table `workflow_taches` (une tâche par passage d'une étape à l'état « à traiter ») + `workflow_tache_evenements` (journal immuable : création, prise en charge, libération, réaffectation, suspension, reprise, commentaire, décision, annulation) | `workflow_instance_etapes` est réécrite lors d'un renvoi (A4) ; le référentiel exige qu'une étape puisse être traitée plusieurs fois (§ 15.2). Tables additives, rattachées aux instances et étapes existantes |
| Création des tâches | `TacheWorkflowService::synchroniser($instance)`, idempotent, appelé par le moteur au démarrage, après chaque décision et après un renvoi, **dans la même transaction** | RG-01, RG-06, RG-19 ; aucun point d'entrée oublié |
| Décisions | `DecisionMetierService` unique (contrôles préalables + moteur + effets métier) utilisé par les 6 contrôleurs existants, l'API générique et « Mes Tâches » | Supprime la duplication (A9), ferme V1 et V2 |
| Concurrence | Verrou `lockForUpdate` sur l'instance dans `WorkflowService::agir()` + revérification de l'étape ; version optimiste sur la tâche (`version`) ; idempotence (`Idempotency-Key`) | RG-07, RG-14, § 16.2 |
| Affectation | Éligibilité calculée par la règle d'habilitation réelle (rôle, structure, délégation, intérim, contrôle Direction du Budget, exclusion de l'initiateur) ; administrateur exclu de la file de travail (A8 documenté, non modifié au niveau du moteur) | § 6 |
| Interface | Pages Blade « Mes tâches » (tableau de bord, onglets, filtres, pagination serveur, fiche de traitement) + API `/api/v1/tasks` consommée par la vue SPA existante | Interface réellement utilisée = Blade ; SPA alignée sur la même API |
| Droits | « Mes tâches à traiter » accessible à tout utilisateur authentifié (périmètre = tâches qu'il peut traiter) ; vues unité / supervision / exports sous `workflow.view` / `workflow.audit` | Fonctionne sans exécuter `WorkflowPermissionsSeeder` (autorisation requise) |

## Lots ordonnés

| Lot | Contenu | Fichiers | Dépend de | Risque | Tests |
|---|---|---|---|---|---|
| L1 | Migration additive `workflow_taches`, `workflow_tache_evenements` + modèles | `database/migrations/2026_10_25_100000_*`, `app/Models/WorkflowTache*.php` | — | Faible (additif) | Migration sur base de test |
| L2 | `TacheWorkflowService` (synchronisation, référence, module, unité, priorité calculée) ; appels dans `WorkflowService` ; verrou de décision ; notification après renvoi | `app/Services/Workflow/TacheWorkflowService.php`, `WorkflowService.php` | L1 | Moyen (moteur partagé) | `Workflow*Test`, `ChaineDepense*Test`, nouveaux tests |
| L3 | `DecisionMetierService` ; refactorisation des 6 contrôleurs + API générique | `app/Services/Workflow/DecisionMetierService.php`, contrôleurs `agir()` | L2 | Moyen | Tests chaîne de dépense, test RIB Blade |
| L4 | `TacheQueryService` (listes, filtres, tri, pagination, statistiques, supervision) + `TacheActionService` (prise en charge, libération, réaffectation, suspension, reprise, commentaire, décision versionnée) | `app/Services/Workflow/Tache*.php` | L2, L3 | Faible | Tests de service |
| L5 | API REST `/api/v1/tasks/*` + ressource | `TachesApiController`, `routes/api.php` | L4 | Faible | Tests API, autorisations |
| L6 | Interface Blade (tableau de bord, onglets, filtres, fiche, chronologie, pièces GED, commentaires, actions, export CSV) + entrée de menu ; vue SPA alignée | `resources/views/taches/*`, `TacheController`, `config/menu.php`, `frontend/src/views/WorkflowCorbeilleView.vue` | L5 | Faible | Tests HTTP, contrôle visuel Chrome |
| L7 | Notifications liées à la tâche ; commande de reprise `taches:synchroniser` (dossiers en cours) | `WorkflowNotificationService`, commande | L2 | Faible | Test notification |
| L8 | Seeders : Commissaire et Agent comptable dans `WorkflowPermissionsSeeder`, appel dans `DatabaseSeeder` (code seulement, **non exécuté sur la base**) | seeders | — | Nul sur les données | — |
| L9 | Recette, non-régression, empreintes après, rapports 04 à 08 | `docs/audits/mes-taches/` | tous | — | — |

## Opérations soumises à autorisation (non exécutées)

1. Exécution de `WorkflowPermissionsSeeder` sur la base (ajout de permissions `workflow.*` aux rôles) — non bloquant (les droits `workflow.*` sont résolus par `WorkflowPermissionAliases`), recommandé pour rendre ces droits explicites et auditables dans la gestion des rôles.
2. Retrait du pouvoir décisionnel implicite de l'administrateur dans le moteur (A8) — règle institutionnelle.
