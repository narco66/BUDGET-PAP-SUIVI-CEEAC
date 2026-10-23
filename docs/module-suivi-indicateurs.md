# Module Suivi des Indicateurs — BUDGET-PAP-SUIVI-CEEAC

Pilotage IOV (Indicateurs Objectifs Vérifiables) du cadre RBM/GAR, avec historisation et snapshots persistés (phases P0 → P3).

## 1. Périmètre fonctionnel

| Fonction | Implémentation |
|---|---|
| Saisie et validation des mesures | `MesureService` |
| Historique des mesures | Table `historique_mesures` (P0) |
| Blocage mesure validée | `MesureService` (P0) |
| Calcul taux / feu (vert, orange, rouge) | `IndicateurPerformanceService` |
| Tendance période N vs N-1 | `IndicateurPerformanceService::tendance()` |
| Snapshots persistés | Table `performances_indicateurs` (P2) |
| Hub pilotage | `/rbm/suivi-indicateurs` (P1) |

## 2. Architecture

```
app/Services/Rbm/
  IndicateurPerformanceService.php   KPI, tendances, snapshots
  MesureService.php                  Saisie, validation, historisation

app/Models/
  PerformanceIndicateur.php          Snapshot IOV par période

app/Http/Controllers/Rbm/
  SuiviIndicateursController.php     Hub web

app/Console/Commands/
  CapturerPerformancesIndicateurs.php   indicateurs:capture-performances
```

## 3. Snapshots `performances_indicateurs`

Capturés automatiquement à la **validation** d'une mesure (`MesureService::valider()`).

Champs : `exercice_id`, `indicateur_id`, `periode`, `valeur_cible`, `valeur_realisee`, `taux_atteinte`, `feu`, `mesure_id`, `capture_par`, `capture_le`.

```bash
# Recapture batch pour un exercice
php artisan indicateurs:capture-performances --exercice=2026
```

## 4. Permissions

Accès hub : permission `indicateur.voir` (incluse dans `*.voir` pour la plupart des rôles métier).

## 5. Routes web

| GET | `/rbm/suivi-indicateurs` | `rbm.suivi-indicateurs.hub` |

Affiche : feux globaux, évolution IOV, alertes rouges, compteur snapshots.

## 6. Rapports associés

- `indicateurs_evolution` — synthèse IOV avec tendances (P1)

## 7. API REST (P3)

Base : `/api/v1` — scopes Sanctum `lecture` ou `suivi` + permission `indicateur.voir`.

| GET | Endpoint | Description |
|---|---|---|
| `/suivi-indicateurs/feux?exercice=` | Répartition vert/orange/rouge |
| `/suivi-indicateurs/evolution?exercice=` | Synthèse IOV avec tendances |
| `/suivi-indicateurs/performances?exercice=` | Snapshots persistés |

Détail : [api-suivi-financier-indicateurs.md](api-suivi-financier-indicateurs.md).

## 8. Tests automatisés

```bash
php artisan test --filter=SuiviIndicateursP0Test,SuiviIndicateursP1Test,SuiviIndicateursP2Test,SuiviP3Test
```

## 9. Déploiement production

1. `php artisan migrate` (table `performances_indicateurs`)
2. `php artisan permissions:sync-production --force`
3. Vérifier le hub avec un compte disposant de `indicateur.voir`
