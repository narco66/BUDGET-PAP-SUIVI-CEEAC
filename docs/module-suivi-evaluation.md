# Module Suivi-Évaluation — BUDGET-PAP-SUIVI-CEEAC

Pilotage institutionnel S&E : scores agrégés, évaluations périodiques, missions, plans d'amélioration (phases P0 → P3).

## 1. Périmètre fonctionnel

| Phase | Fonction | Implémentation |
|---|---|---|
| P0 | Calcul scores unifiés | `SuiviEvaluationCalculator` |
| P0 | Snapshots évaluations | `evaluations_periodiques` |
| P0 | Permissions & hub | `suivi_evaluation.*`, `/suivi-evaluation` |
| P1 | Rapport institutionnel | Type `suivi_evaluation` |
| P1 | Capture batch | `suivi-evaluation:capture` |
| P1 | Détail évaluation | `/suivi-evaluation/evaluations/{id}` |
| P1 | Auto-capture clôture campagne | `CampagneSuiviController::cloturer` |
| P2 | Missions S&E | Table `missions_suivi` |
| P2 | Plans d'amélioration | `plans_amelioration` + `actions_correctives` |
| P3 | API REST | `/api/v1/suivi-evaluation/*` |
| P3 | Données démo | `SuiviEvaluationDemoSeeder` |

## 2. Architecture

```
app/Services/Suivi/
  SuiviEvaluationCalculator.php
  SuiviEvaluationService.php

app/Models/
  EvaluationPeriodique.php
  MissionSuivi.php
  PlanAmelioration.php
  ActionCorrective.php

app/Http/Controllers/
  SuiviEvaluationController.php
  MissionSuiviController.php
  PlanAmeliorationController.php

app/Console/Commands/
  CapturerEvaluationsPeriodiques.php
```

## 3. Chaîne S&E (P2)

```text
Évaluation périodique → Mission de suivi → Plan d'amélioration → Actions correctives ↔ Recommandations
```

## 4. Routes web

| Méthode | Route | Description |
|---|---|---|
| GET | `/suivi-evaluation` | Hub pilotage |
| POST | `/suivi-evaluation/generer` | Générer évaluation annuelle |
| GET | `/suivi-evaluation/evaluations/{id}` | Détail snapshot |
| GET/POST | `/suivi-evaluation/missions/*` | Missions S&E |
| GET/POST | `/suivi-evaluation/plans/*` | Plans & actions |

## 5. Commandes

```bash
php artisan suivi-evaluation:capture --exercice=2026
```

## 6. API REST (P3)

Base : `/api/v1` — scopes `lecture` ou `suivi` + permission `suivi_evaluation.view` (ou alias).

| GET | Endpoint | Description |
|---|---|---|
| `/suivi-evaluation/synthese?exercice=` | Scores courants et stats |
| `/suivi-evaluation/evaluations?exercice=` | Historique snapshots |
| `/suivi-evaluation/missions?exercice=` | Missions S&E |
| `/suivi-evaluation/plans?exercice=` | Plans d'amélioration |

Détail : [api-suivi-financier-indicateurs.md](api-suivi-financier-indicateurs.md).

## 7. Tests automatisés

```bash
php artisan migrate
php artisan test tests/Feature/SuiviEvaluationP0Test.php tests/Feature/SuiviEvaluationP1Test.php tests/Feature/SuiviEvaluationP2Test.php tests/Feature/SuiviEvaluationP3Test.php
```

## 8. Déploiement production

1. `php artisan migrate`
2. `php artisan permissions:sync-production --force`
3. (Optionnel démo) `php artisan db:seed --class=Database\\Seeders\\SafeDatabaseSeeder`

## 9. Recette MOA (post-P3)

| Document | Référence | Date |
|----------|-----------|------|
| [pv-recette-suivi-evaluation.md](pv-recette-suivi-evaluation.md) | PV-REC-SE-2026-01 | Recette 09/07/2026 |
| [convocation-demo-DEMO-SE-2026-01.md](convocation-demo-DEMO-SE-2026-01.md) | DEMO-SE-2026-01 | Démo 03/07/2026 |
| [convocation-recette-PV-REC-SE-2026-01.md](convocation-recette-PV-REC-SE-2026-01.md) | Convocation | — |
| [annuaire-recette-suivi-evaluation-2026.md](annuaire-recette-suivi-evaluation-2026.md) | Personas & comptes test | — |
| [checklist-animateur-DEMO-SE-2026-01.md](checklist-animateur-DEMO-SE-2026-01.md) | Checklist imprimable démo | 03/07/2026 |
| [compte-rendu-demo-DEMO-SE-2026-01.md](compte-rendu-demo-DEMO-SE-2026-01.md) | CR post-démo | CR-DEMO-SE-2026-01 |
| [checklist-animateur-PV-REC-SE-2026-01.md](checklist-animateur-PV-REC-SE-2026-01.md) | Checklist imprimable recette | 09/07/2026 |

**Grille :** 54 scénarios (Blocs A–J) · seuil MOA ≥ 95 % sans NC bloquante sur Blocs A, B, F.
