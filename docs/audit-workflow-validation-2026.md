# Audit du module Workflow et Validation
## BUDGET-PAP-SUIVI-CEEAC — Commission de la CEEAC

**Référence :** AUD-WFV-2026-01  
**Date :** 13 juin 2026  
**Périmètre :** Moteur D14, circuits métier, visa, rejets, traçabilité, intégration modules  
**Méthode :** Revue code, schéma BDD, seeders, policies, tests, UI (sans `migrate:fresh`)

---

## Synthèse exécutive

| Indicateur | Valeur |
|------------|--------|
| **Score global de conformité** | **~54 %** (cahier des charges « moteur enterprise ») |
| **Score opérationnel chaîne financière** | **~74 %** (PAP, Budget enveloppe, Dépenses D14) |
| **Verdict** | **Socle fonctionnel solide** pour validation séquentielle hiérarchique sur les objets critiques, mais **non conforme** à l’objectif d’un **moteur central unique** pilotant l’ensemble des modules avec visa électronique unifié, paramétrage avancé et gouvernance transverse. |

**Points forts :** `WorkflowService` (D14), 7 circuits seedés, historique par instance, résolution institutionnelle (organigramme + délégations), tests PAP dédiés, intégration PAP/Budget/Dépense mature.

**Points critiques :** absence permissions `workflow.*`, double/triple moteur (D14 / GED / Reporting / Stratégie), Recettes et Suivi hors circuit, pas de versioning/conditions/parallélisme, visa non généralisé, pas de corbeille transverse ni SLA.

---

## 1. Niveau global de conformité

| Domaine audité | Score | Commentaire |
|----------------|-------|-------------|
| Moteur configurable (CRUD, versions, conditions) | 22 % | Circuits en seeder uniquement |
| Circuits attendus CEEAC | 68 % | PAP/Budget OK ; Dépenses fragmentée ; Recettes absent |
| Visa électronique | 42 % | Dispersé (engagement, GED, reporting) |
| Approbation hiérarchique | 70 % | Séquentielle + renvoi ; pas parallèle/conditionnel |
| Historique validations | 72 % | `workflow_instance_etapes` + timeline UI |
| Gestion rejets | 58 % | Motif texte ; pas de typologie centralisée |
| Intégration 12 modules | 48 % | 4 moteurs parallèles |
| Tableaux de bord workflow | 14 % | Timelines locales ; pas de hub global |
| Alertes / notifications | 46 % | PAP, Budget, GED partiels |
| Sécurité / séparation fonctions | 62 % | Rôles par étape ; bypass admin ; multi-rôles |
| Permissions `workflow.*` | 0 % | Non déclarées |
| Couverture tests | 58 % | 1 suite dédiée PAP ; reste indirect |

---

## 2. Circuits conformes

### PAP — **Conforme (~90 %)**

Circuit seedé `PAP` (6 étapes) :

```text
chef_service → directeur → commissaire → controleur_financier → secretaire_general → president
```

Aligné sur le schéma attendu (Service → Direction → Département → DPPB → validation finale).  
Fichier : `database/seeders/WorkflowSeeder.php`  
Moteur : `app/Services/WorkflowService.php`  
Objet : `app/Models/Pap.php` (`PilotableParWorkflow`)

### Budget (enveloppe) — **Conforme (~88 %)**

Circuit `BUDGET` — même structure que PAP jusqu’à adoption présidentielle.  
Controller : `BudgetEnveloppeController` (soumettre / agir).

### Expression du besoin — **Partiellement conforme (~75 %)**

Circuit `BESOIN` (3 étapes) : chef_service → directeur → controleur_financier.  
Couvre le début de la chaîne dépense ; le « demandeur » n’est pas une étape workflow distincte.

### Chaîne dépense aval — **Conforme en séparation des fonctions (~80 %)**

| Code | Rôle | Objet |
|------|------|-------|
| `ENGAGEMENT` | controleur_financier | Visa engagement |
| `LIQUIDATION` | controleur_financier | Contrôle |
| `ORDONNANCEMENT` | secretaire_general | Ordonnateur |
| `PAIEMENT` | comptable | Exécution |

Correspond à la logique CEEAC (CF / ordonnateur / comptable), mais **fragmentée en 4 circuits** au lieu d’un circuit unique « Dépenses ».

### GED — **Conforme sur périmètre documentaire (~75 %)**

Moteur séparé : `ged_workflows`, `ged_validations`, `ged_signatures`  
Service : `app/Services/Ged/GedWorkflowService.php`

---

## 3. Circuits manquants ou non branchés

| Circuit attendu | Statut | Détail |
|-----------------|--------|--------|
| **Recettes** (Gestionnaire → CF → Comptable) | **Absent** | `EncaissementController::valider` — validation directe |
| **Suivi physique** (collectes) | **Absent** | Vérification → validation manuelle (`CollecteController`) |
| **Suivi financier** | **Absent** | Pas de circuit sur situations / restes |
| **Suivi indicateurs** | **Absent** | Snapshots sans workflow |
| **Suivi-évaluation** | **Absent** | Évaluations / missions hors D14 |
| **Reporting institutionnel** | **Moteur parallèle** | `RapportWorkflowService` (genere→valide→publie) |
| **Planification stratégique** | **Moteur parallèle** | `StrategieValidationService` |
| **Paramètres / délégations** | **Hors workflow** | `Delegation` gouverne substitution, pas circuits |
| **Budget — préparations / arbitrages** | **Partiel** | `BudgetPreparationService::valider` sans D14 |
| **Workflows parallèles / conditionnels** | **Absent** | Architecture séquentielle fixe |

---

## 4. Étapes manquantes

| Écart | Impact |
|-------|--------|
| Pas d’étape « Demandeur » explicite sur besoin | Traçabilité initiale faible |
| Recettes sans étapes CF / comptable | Non-conformité procédure contributions |
| Collecte : pas d’étape « vérification » dans D14 | Circuit implicite (statuts) non historisé uniformément |
| Pas d’étape « mise en attente » | Blocages non formalisés |
| `delai_jours` sur `workflow_etapes` **jamais exploité** | SLA / relances impossibles |
| Visa cryptographique absent des étapes PAP/Budget | « Visa DPPB » = libellé rôle, pas signature |

---

## 5. Risques de gouvernance

| # | Risque | Gravité |
|---|--------|---------|
| R-G1 | **Multiples moteurs** (D14, GED, Reporting, Stratégie) — gouvernance fragmentée | Élevée |
| R-G2 | Circuits **non modifiables** sans déploiement (seeder / code) | Élevée |
| R-G3 | Absence **UI admin** circuits D14 (vs GED admin seulement) | Moyenne |
| R-G4 | Recettes et suivi **hors circuit officiel** | Élevée |
| R-G5 | Pas de **registre central** des validations transverses | Moyenne |

---

## 6. Risques de contrôle interne

| # | Risque | Gravité |
|---|--------|---------|
| R-CI1 | Utilisateur **multi-rôles** peut enchaîner plusieurs étapes (aucun contrôle anti-auto-validation inter-étapes) | Moyenne |
| R-CI2 | Rôle **administrateur** bypass `peutAgir` | Moyenne |
| R-CI3 | Validations budget préparation **sans workflow** | Moyenne |
| R-CI4 | Pas de **verrouillage systématique** post-validation intermédiaire (selon objet) | Faible |
| R-CI5 | Paramètre `workflow.relance_jours` seedé mais **non implémenté** | Moyenne |

---

## 7. Risques de fraude

| # | Risque | Gravité |
|---|--------|---------|
| R-F1 | Validation recettes **sans double contrôle** workflow | Élevée |
| R-F2 | Visa engagement ≠ signature forte sur toutes validations | Moyenne |
| R-F3 | Contournement possible si permissions module (`*.valider`) sans instance active | Faible (policies liées à `workflowActif`) |
| R-F4 | Absence catégorisation rejets → **analyse forensique** difficile | Faible |

---

## 8. Risques de traçabilité

| # | Risque | Gravité |
|---|--------|---------|
| R-T1 | IP / user-agent sur **journaux_audit** global, pas sur `workflow_instance_etapes` | Faible |
| R-T2 | Pas de table **`audit_workflow`** consultable MOA | Moyenne |
| R-T3 | Rejets collecte **sans motif obligatoire** en BDD | Moyenne |
| R-T4 | Historique PAP complété par `pap_historiques` — **modèle hétérogène** | Faible |

**Existant positif :** `AuditLogger` → `journaux_audit` (IP, user_agent, avant/après) ; actions `workflow_valide`, `workflow_rejete`, `workflow_renvoye`.

---

## 9. Recommandations

1. **Unifier la gouvernance** : faire converger GED / Reporting / Stratégie vers D14 ou une couche `WorkflowFacade` unique.
2. **Créer permissions `workflow.*`** et UI admin CRUD circuits (sans seeder obligatoire).
3. **Brancher Recettes** sur circuit `RECETTES` (3 étapes).
4. **Généraliser le visa** : table `visas_electroniques` liée aux instances D14 (hash, certificat, IP).
5. **Corbeille transverse** `/workflow/validations` pour tous validateurs.
6. **Implémenter SLA** : exploitation `delai_jours`, relances, escalade.
7. **Renforcer SoD** : interdire même acteur sur N étapes consécutives ; journaliser conflits.
8. **Typologie rejets** : enum + table `motifs_rejet`.
9. **Étendre tests** : Budget, Dépense, Recettes, délégation, SoD.
10. **Dashboard workflow global** : KPI attente / délais / taux rejet.

---

## 10. Plan de correction priorisé

### P0 — Gouvernance & sécurité (4 semaines)

| # | Action | Livrable |
|---|--------|----------|
| W-P0-1 | Seeder `WorkflowPermissionsSeeder` (`workflow.view` … `workflow.audit`) | Permissions + policies |
| W-P0-2 | Contrôle SoD inter-étapes (même user ≠ 2 validations consécutives) | `WorkflowService` |
| W-P0-3 | Motif rejet obligatoire + catégorie sur tous `rejeter()` | Controllers + migration |
| W-P0-4 | Circuit **RECETTES** + branchement encaissements | Migration + controller |

**Statut (juin 2026) : livré** — `WorkflowPermissionsSeeder`, `MotifsRejetSeeder`, SoD dans `WorkflowService`, `WorkflowDecisionRules` + partial `_workflow_agir`, circuit RECETTES sur encaissements, tests `WorkflowP0Test`.

### P1 — Paramétrage & traçabilité (6 semaines)

| # | Action | Livrable |
|---|--------|----------|
| W-P1-1 | UI admin circuits D14 (`/admin/workflows`) | CRUD + activation |
| W-P1-2 | Table `audit_workflow` ou vue MOA sur `journaux_audit` | Page audit |
| W-P1-3 | Corbeille validations transverse | Hub + notifications |
| W-P1-4 | Exploitation `delai_jours` + relances | Command + scheduler |
| W-P1-5 | Tests `WorkflowBudgetTest`, `WorkflowDepenseTest`, `WorkflowRecettesTest` | CI |

**Statut (juin 2026) : livré** — admin `/admin/workflows`, corbeille `/workflow/validations`, audit `/workflow/audit`, commande `workflow:relances`, SLA `delai_jours` + tests P1.

### P2 — Visa & convergence (8 semaines)

| # | Action | Livrable |
|---|--------|----------|
| W-P2-1 | `visas_electroniques` + lien instance D14 | Service unifié |
| W-P2-2 | Rapport / Stratégie → hooks D14 (ou statuts mappés) | Refactoring |
| W-P2-3 | Collectes suivi → circuit `COLLECTE` | Campagne / collecte |
| W-P2-4 | Dashboard performance workflow | KPI + exports |

**Statut (juin 2026) : livré** — `visas_electroniques`, convergence RAPPORT/STRATEGIE/COLLECTE sur D14, dashboard `/workflow/dashboard`, timeline enrichie (empreinte visa).

### P3 — Enterprise (optionnel)

| # | Action |
|---|--------|
| W-P3-1 | `workflow_versions`, `workflow_conditions`, `workflow_transitions` |
| W-P3-2 | Workflows parallèles et conditionnels |
| W-P3-3 | API REST `/api/v1/workflow/*` |

**Statut (juin 2026) : livré** — tables `workflow_versions`, `workflow_conditions`, `workflow_transitions`, moteur parallèle/conditionnel, API REST Sanctum.

---

## 11. Tables à créer

| Table | Priorité | Motif |
|-------|----------|-------|
| `audit_workflow` | P1 | Journal consultable MOA (ou vue matérialisée) |
| `visas_electroniques` | P2 | Visa unifié (user, hash, IP, type visa) |
| `motifs_rejet` | P0 | Référentiel catégories rejet |
| `rejets` | P1 | Historique centralisé rejets |
| `workflow_versions` | P3 | Versionning circuits |
| `workflow_conditions` | P3 | Branches conditionnelles |
| `workflow_transitions` | P3 | Graphe d’états |
| `validations` | P2 | Registre transverse (optionnel si D14 enrichi) |
| `historique_validations` | P2 | Alias consolidé (vue) |

---

## 12. Colonnes à ajouter

| Table | Colonnes | Motif |
|-------|----------|-------|
| `workflows` | `version`, `published_at`, `created_by` | Versionning |
| `workflow_instances` | `version_workflow`, `objet_version` | Lien version document |
| `workflow_instance_etapes` | `ip`, `user_agent`, `categorie_visa`, `hash_visa` | Traçabilité / visa |
| `workflow_etapes` | `type_approbation` (sequentiel/parallele), `condition_json` | Paramétrage |
| `expressions_besoin` / entités | `categorie_rejet` | Typologie rejet |
| `collectes` | `motif_rejet`, `categorie_rejet` | Traçabilité S&E |

---

## 13. Relations à corriger

| Relation | Action |
|----------|--------|
| `workflow_instances` morph → objets métier | OK — étendre à Recettes, Collecte |
| `ged_validations` ↔ D14 | **Unifier** ou mapper |
| `rapports.statut_workflow` ↔ D14 | Documenter mapping ou fusionner |
| `delegations` ↔ `WorkflowInstitutionResolver` | OK — documenter + tester |
| `paiement_rejets` → `rejets` | Centraliser FK |
| `workflow_etape_id` → versions | FK vers version circuit active |

---

## 14. Interfaces à développer

| Interface | Route proposée | Priorité |
|-----------|----------------|----------|
| Corbeille validations | `/workflow/validations` | P1 |
| Admin circuits D14 | `/admin/workflows` | P1 |
| Audit workflow MOA | `/workflow/audit` | P1 |
| Dashboard global | `/workflow/dashboard` | P2 |
| Configuration relances | `/workflow/parametres` | P1 |
| Historique dossier | *(existe)* `_timeline.blade.php` | Enrichir IP / visa |
| Validations GED | `/ged/validations` | Existant |

---

## 15. Tests à ajouter

| Fichier | Scénarios |
|---------|-----------|
| `WorkflowPapTest` | **Existant** (6) — conserver |
| `WorkflowBudgetTest` | Soumission, adoption, rejet, renvoi |
| `WorkflowDepenseTest` | Chaîne besoin→engagement→paiement |
| `WorkflowRecettesTest` | Circuit 3 niveaux encaissement |
| `WorkflowSoDTest` | Même user bloqué sur 2 étapes |
| `WorkflowDelegationTest` | Délégation active → `peutAgir` |
| `WorkflowAuditTest` | Ligne `journaux_audit` + IP |
| `WorkflowRelanceTest` | Dépassement `delai_jours` |
| `WorkflowPermissionsTest` | `workflow.validate` / refus |
| `WorkflowGedConvergenceTest` | *(P2)* mapping GED ↔ D14 |

**État actuel :** ~30 fichiers Feature référencent workflow (surtout Depense/Budget/PAP indirect) ; **1 seul** test unitaire dédié au moteur (`WorkflowPapTest.php`).

---

## Annexe A — Inventaire tables

### Présentes (moteur D14)

- `workflows`, `workflow_etapes`, `workflow_instances`, `workflow_instance_etapes`

### Présentes (GED)

- `ged_workflows`, `ged_workflow_etapes`, `ged_validations`, `ged_signatures`

### Présentes (audit / rejet partiel)

- `journaux_audit`, `paiement_rejets`, `delegations`

### Absentes (cahier audit)

- `workflow_versions`, `workflow_conditions`, `workflow_transitions`
- `validations`, `validation_actions`, `validation_decisions`, `validation_commentaires`
- `visas_electroniques`, `signatures_validation`
- `rejets`, `motifs_rejet`, `corrections`
- `historique_validations`, `audit_workflow`

---

## Annexe B — Intégration par module

| Module | Moteur | Score intégration |
|--------|--------|-------------------|
| Planification stratégique | `StrategieValidationService` | 25 % |
| PAP | D14 | 90 % |
| Budget (enveloppe) | D14 | 88 % |
| Budget (préparation) | Direct | 30 % |
| Recettes | Direct | 10 % |
| Dépenses | D14 (multi-circuits) | 82 % |
| Suivi physique | Statuts collecte | 20 % |
| Suivi financier | — | 0 % |
| Suivi indicateurs | — | 0 % |
| Suivi-évaluation | — | 15 % |
| Reporting | `RapportWorkflowService` | 40 % |
| GED | `GedWorkflowService` | 75 % (périmètre GED) |
| Paramètres | Délégations | 35 % |

---

## Annexe C — Fichiers de référence

```
app/Services/WorkflowService.php
app/Services/Institution/WorkflowInstitutionResolver.php
database/seeders/WorkflowSeeder.php
app/Contracts/PilotableParWorkflow.php
app/Models/Concerns/HasWorkflow.php
app/Support/AuditLogger.php
tests/Feature/WorkflowPapTest.php
resources/views/partials/_timeline.blade.php
```

---

**Document produit dans le cadre de l’audit institutionnel BUDGET-PAP-SUIVI-CEEAC — sans modification destructive de la base.**
