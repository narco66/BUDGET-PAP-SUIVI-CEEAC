# Rapport d'implémentation — module Workflow

**Date :** 25 octobre 2026 · **Audit :** `docs/audits/workflow/01_AUDIT_MODULE_WORKFLOW.md`
**Principe appliqué :** conserver le moteur existant (conforme sur l'essentiel) et corriger les manquements A1 → A6 ; documenter A7 → A10.

## 1. Corrections réalisées

| # | Manquement | Correction | Vérification |
|---|---|---|---|
| A1 | Retirer une étape d'un circuit effaçait en cascade les décisions de tous les dossiers | Clé étrangère `workflow_instance_etapes.workflow_etape_id` en **RESTRICT** ; l'administration refuse de retirer une étape rattachée à des décisions (message explicite) | `test_a1_…` ; PostgreSQL : suppression refusée (transaction annulée) |
| A2 | Versionnement inopérant (0 version, 0 instance épinglée) | Chaque création/modification de circuit **publie une version** (instantané immuable, empreinte, journal `workflow_version_publiee` avec avant/après) ; la version précédente passe « retirée » ; le démarrage épingle l'instance à la version publiée (publication initiale automatique si besoin) ; **15 versions initiales publiées** (`workflow:versions-initiales`) | `test_wf020_…` |
| A3 | Aucune validation de publication | Validation bloquante (§ 7.2) : au moins une étape, ordres positifs et uniques, libellé, rôle existant, délai positif, transitions vers des étapes existantes, étapes utilisées non retirées ; tout ou rien (transaction) | `test_a3_…` |
| A4 | Double démarrage possible | Verrou du dossier et nouveau contrôle **dans** la transaction ; **index unique partiel** `workflow_instances (objet_type, objet_id) WHERE statut = 'en_cours'` | `test_wf004_…`, `test_wf004b_…` ; PostgreSQL : doublon refusé |
| A5 | Libellé d'étape lu dans la définition vivante | Colonne `libelle` sur l'instance (232/232 rétro-alimentées), écrite au démarrage, affichée par la ligne du temps (heure de Libreville) | `test_wf020_…` |
| A6 | API de décision | **403** non habilité, **409** dossier déjà traité / étape changée, **422** règle métier ; motif obligatoire pour rejet et retour, **catégorie de rejet obligatoire** (plus de valeur « technique » imposée) ; `etape_attendue_id` ; **clé d'idempotence** (`Idempotency-Key`) ; **historique** (acteur, motif, catégorie, empreinte de visa), libellés, version dans le détail ; **refus tracés** au journal central (`workflow_decision_refusee`) | `test_wf010_…`, `test_wf013_…`, `test_wf018_…`, `test_detail_instance_…` |

## 2. Conservé sans modification

Moteur de décision (verrou d'instance, étape attendue, visas, audit), séparation des fonctions et journal des violations, délégations et intérims (revérifiés à chaque décision — `test_wf014_…` : délégation expirée refusée), résolution des valideurs, circuits EB PAP / appui / hors PAP / ENG / LIQ / ORD (seuil) / PAY, transitions aval idempotentes, Mes Tâches, notifications, rappels et escalade, tableau de bord, corbeille, écrans d'audit. Aucune route supprimée.

## 3. Base de données

Migration `2026_10_25_410000_workflow_conformite` (réversible), appliquée sur PostgreSQL 18 après sauvegarde `pg_dump` des tables du workflow :
- clé étrangère `workflow_etape_id` : `CASCADE` → `RESTRICT` ;
- colonne `workflow_instance_etapes.libelle` (rétro-alimentée) ;
- index unique partiel `workflow_instances_une_en_cours_par_objet`.

Données : 15 versions publiées (`workflow_versions`), `workflows.version` inchangé (1). Les 122 instances existantes ne sont **pas** rattachées rétroactivement à ces versions (elles ont démarré avant) : leurs étapes copiées font foi.

## 4. Fichiers

**Créés :** `database/migrations/2026_10_25_410000_workflow_conformite.php`, `app/Console/Commands/WorkflowVersionsInitialesCommand.php`, `database/scripts/empreinte_historique_workflow.php`, `tests/Feature/WorkflowConformiteTest.php`, `docs/audits/workflow/*`.
**Modifiés :** `app/Services/WorkflowService.php` (démarrage sous verrou, version, libellé), `app/Services/Workflow/WorkflowVersionService.php`, `app/Services/Workflow/WorkflowCircuitAdminService.php`, `app/Http/Controllers/Api/V1/WorkflowApiController.php`, `app/Models/WorkflowInstanceEtape.php`, `resources/views/partials/_timeline.blade.php`.

## 5. Recette (scénarios du référentiel § 23)

| ID | Scénario | Couverture |
|---|---|---|
| WF-001/002 | EB PAP : Directeur puis Commissaire ou SG | `WorkflowConformiteTest::test_wf001_wf002_…`, `MesTachesTest` |
| WF-003 | EB → engagement unique | `test_wf003_…` (+ index unique existant) |
| WF-004 | Double clic | `test_wf004_…`, `test_wf004b_…` |
| WF-005 | ENG sans crédits | Tests existants de la chaîne de dépense (`ChaineDepense*`, `DepenseD*`) |
| WF-006/007 | Visa ENG → LIQ, visa LIQ → ORD uniques | `ChaineDepenseAvalConformiteTest`, `DepenseAvalTest` |
| WF-008/009 | ORD au seuil / au-dessus | `ChaineDepenseAvalConformiteTest::test_seuil_…`, `NotificationsConformiteTest::test_not006_not007_…` |
| WF-010 | Acteur non habilité | `test_wf010_…` (403 + trace) |
| WF-011/012 | Paiement partiel / soldé | `NotificationsConformiteTest::test_not008_…`, tests de paiement existants |
| WF-013 | Retour sans motif | `test_wf013_…` |
| WF-014 | Délégation expirée | `test_wf014_…` |
| WF-015 | Réaffectation | `MesTachesTest`, `NotificationsConformiteTest::test_not018_…` |
| WF-016/017 | Notification cliquable, lue ≠ traitée | `NotificationsConformiteTest::test_not009_…`, `test_not011_…` |
| WF-018 | Concurrence | `test_wf018_…` (409, idempotence) |
| WF-019 | Échec job aval | 🟡 effets aval exécutés dans la transaction de décision (pas de transition fantôme) ; pas de file de reprise dédiée (A8) |
| WF-020 | Nouvelle version | `test_wf020_…` |
| WF-021 | Accès d'un autre service | Tests de périmètre existants (`MesTachesTest` RG-15, cloisonnement) |
| WF-022 | Migration sans altération | Empreintes avant/après (§ 6) |

## 6. Préservation des données

- Données protégées (21 catégories) : identiques, sauf le journal central, qui reçoit **15 écritures ajoutées** (`workflow_version_publiee`) ; les **3 071 écritures antérieures** ont exactement l'empreinte d'origine (`e9db955c…`).
- Historique du workflow (circuits, étapes, 122 instances, 232 décisions, 213 visas, 336 audits, 220 tâches) : **identique** (`empreinte_historique_avant/apres.json`).
- Vérifications PostgreSQL en transactions annulées ; aucune commande destructive.

## 7. Écarts résiduels (arbitrages requis, référentiel § 26)

1. **A7 — Pièces obligatoires par étape** non paramétrables (contrôles codés par module : service fait, RIB…).
2. **A8 — Outbox** : pas de file d'événements durable ni d'écran de réconciliation des échecs asynchrones.
3. **A9 — Annulation générique** d'une instance : dépend des règles de compensation après effet comptable, à arrêter.
4. **A10 — Gouvernance de publication** : la publication est immédiate pour un titulaire de `workflow.update` ; le circuit rédacteur → vérificateur → approbateur et la date d'effet différée ne sont pas outillés ; pas d'écran de comparaison des versions (instantanés consultables en base et dans le journal).
5. Simulation de parcours (§ 7) non outillée.
6. Interfaces : Blade/Bootstrap (et SPA Vue) conservées au lieu de React/Tailwind ; pas de tests navigateur (Playwright absent).
