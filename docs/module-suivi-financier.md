# Module Suivi Financier — BUDGET-PAP-SUIVI-CEEAC

Dispositif central de pilotage de l'exécution budgétaire de la Commission de la CEEAC (phases P0 → P3).

## 1. Périmètre fonctionnel

| Indicateur | Source |
|---|---|
| Budget ouvert / adopté (`alloue`) | `lignes_budgetaires` |
| Engagé, liquidé, ordonnancé, payé | Compteurs `lignes_budgetaires` |
| Crédits disponibles | `alloue − engagé` |
| Restes à engager / à payer | `SuiviFinancierCalculator` |
| 6 taux (engagement → exécution) | `SuiviFinancierCalculator::synthese()` |
| Snapshots journaliers | Table `situations_budgetaires` |
| Écarts physique/financier PAP | `PapPerformanceService::ecartsPhysiqueFinancier()` |

**Règle d'or (P0)** : budget adopté = `COALESCE(montant_revise, montant_ouvert)` — identique partout (tableau de bord, reporting, exports).

## 2. Architecture

```
app/Services/Budget/
  SuiviFinancierCalculator.php      Source unique des calculs (P0)
  SuiviFinancierService.php         Orchestration hub (P2)
  SituationBudgetaireService.php    Capture snapshots (P1)
  CompteurMouvementAuditService.php Audit anomalies compteurs

app/Http/Controllers/
  SuiviFinancierController.php      Hub web + anomalies + capture

app/Http/Controllers/Api/V1/
  SuiviApiController.php            Endpoints REST (P3)

resources/views/suivi-financier/    Hub (onglets chaîne, structure, écarts, tâches)
```

## 3. Permissions (`suivi_financier.*`)

| Permission | Rôle |
|---|---|
| `suivi_financier.view` | Consulter le hub |
| `suivi_financier.export` | Exports restes, rapports |
| `suivi_financier.validate` | Capturer une situation budgétaire |
| `suivi_financier.admin` | Administration module |

**Rôles par défaut** (via `SuiviFinancierPermissionsSeeder`) :

- **Contrôleur financier** : view, export, validate
- **Secrétaire général** : view, export
- **Directeur** : view
- **Administrateur** : toutes

Les utilisateurs disposant déjà de `tableau_bord.voir` ou `rapport.voir` conservent l'accès au hub (compatibilité ascendante).

## 4. Routes web

| Méthode | URI | Nom |
|---|---|---|
| GET | `/suivi-financier` | `suivi-financier.index` |
| GET | `/suivi-financier/anomalies` | `suivi-financier.anomalies` |
| POST | `/suivi-financier/situation-capture` | `suivi-financier.situation.capture` |

Menu latéral : **Suivi Financier** (visible si `suivi_financier.view` ou `tableau_bord.voir`).

## 5. Commandes artisan

```bash
# Capture manuelle d'une situation budgétaire
php artisan budget:situation-capture --exercice=2026

# Synchronisation des permissions en production (P3)
php artisan permissions:sync-production --force
# ou
php artisan db:seed --class=ProductionPermissionsSeeder
```

La capture est aussi déclenchée automatiquement à la clôture d'exercice (`ExerciceService::cloturer()`).

## 6. API REST (P3)

Base : `/api/v1` — authentification Sanctum, scopes `lecture` ou `suivi`.

| GET | Endpoint | Description |
|---|---|---|
| `/suivi-financier/synthese?exercice=` | Synthèse budgétaire (6 taux + restes) |
| `/suivi-financier/ecarts?exercice=` | Écarts physique/financier PAP |
| `/suivi-financier/situations?exercice=&limit=` | Historique snapshots |
| `/suivi-financier/restes-engager?exercice=` | Lignes avec reste à engager |

Détail complet : [api-suivi-financier-indicateurs.md](api-suivi-financier-indicateurs.md).

## 7. Rapports associés

Types `RapportComposer` :

- `situation_budgetaire` — snapshot consolidé
- `ecarts_physique_financier` — rapprochement PAP

## 8. Tests automatisés

```bash
php artisan test --filter=SuiviFinancierP0Test,SuiviFinancierP1Test,SuiviFinancierP2Test,SuiviP3Test
```

## 9. Déploiement production (P3)

1. `php artisan migrate` (sans `migrate:fresh`)
2. `php artisan permissions:sync-production --force`
3. Vérifier l'accès hub avec un compte **Contrôleur financier**
4. Recette MOA : [pv-recette-suivi-financier.md](pv-recette-suivi-financier.md) · [convocation-recette-PV-REC-SF-2026-01.md](convocation-recette-PV-REC-SF-2026-01.md) · [convocation-demo-DEMO-SF-2026-01.md](convocation-demo-DEMO-SF-2026-01.md) · [annuaire-recette-suivi-financier-2026.md](annuaire-recette-suivi-financier-2026.md)
