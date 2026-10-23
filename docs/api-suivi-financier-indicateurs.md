# API REST — Suivi Financier & Suivi Indicateurs (P3)

Extension de l'API v1 (`routes/api.php`) pour l'intégration SIEM, tableaux de bord externes et partenaires techniques.

## Authentification

```http
POST /api/v1/token
Content-Type: application/json

{
  "email": "utilisateur@ceeac.int",
  "password": "********",
  "abilities": ["suivi"]
}
```

**Scopes disponibles** : `lecture` (lecture générale), `suivi` (modules suivi financier/indicateurs), `tompro` (connecteur comptable).

Réponse `201` :

```json
{
  "token": "1|…",
  "type": "Bearer",
  "abilities": ["suivi"]
}
```

Utilisation :

```http
GET /api/v1/suivi-financier/synthese?exercice=2026
Authorization: Bearer {token}
Accept: application/json
```

## Contrôle d'accès

| Couche | Règle |
|---|---|
| Jeton Sanctum | Scope `lecture` **ou** `suivi` |
| Permissions web | Suivi financier : `suivi_financier.view` (ou `tableau_bord.voir` / `rapport.voir`) |
| Permissions web | Suivi indicateurs : `indicateur.voir` (ou `suivi_financier.view`) |

Un jeton valide sans permission métier → `403 Forbidden`.

Tous les appels sont journalisés dans `api_logs` (middleware `api.log`).

## Endpoints Suivi Financier

### GET `/api/v1/suivi-financier/synthese`

Paramètre **obligatoire** : `exercice` (ID ou année).

```json
{
  "data": {
    "exercice_id": 1,
    "budget": {
      "ouvert": 0,
      "alloue": 0,
      "engage": 0,
      "liquide": 0,
      "ordonnance": 0,
      "paye": 0,
      "disponible": 0,
      "reste_a_engager": 0,
      "reste_a_payer": 0,
      "taux_engagement": 0,
      "taux_liquidation": 0,
      "taux_ordonnancement": 0,
      "taux_paiement": 0,
      "taux_execution": 0
    }
  }
}
```

### GET `/api/v1/suivi-financier/ecarts`

Écarts physique/financier par activité PAP (`type_ecart` : `sur-financée`, `sous-financée`, `alignée`).

### GET `/api/v1/suivi-financier/situations`

Historique des snapshots `situations_budgetaires`. Paramètres : `exercice` (requis), `limit` (défaut 30, max 100).

### GET `/api/v1/suivi-financier/restes-engager`

Lignes budgétaires avec reste à engager > 0.

## Endpoints Suivi Indicateurs

### GET `/api/v1/suivi-indicateurs/feux`

```json
{
  "data": {
    "exercice_id": 1,
    "feux": { "vert": 12, "orange": 3, "rouge": 1 }
  }
}
```

### GET `/api/v1/suivi-indicateurs/evolution`

Collection de synthèses IOV (code, libellé, cible, dernière période, taux, feu, tendance).

### GET `/api/v1/suivi-indicateurs/performances`

Snapshots persistés (`performances_indicateurs`) avec code/libellé indicateur.

## Codes de réponse

| Code | Signification |
|---|---|
| 401 | Jeton absent ou invalide |
| 403 | Scope ou permission métier insuffisant |
| 422 | Paramètre `exercice` manquant |
| 200 | Succès |

## Synchronisation permissions production

Après chaque déploiement incluant de nouvelles permissions :

```bash
php artisan permissions:sync-production --force
```

Seeder idempotent : `ProductionPermissionsSeeder` (rôles + permissions modules, sans données de démo).

## Tests

```bash
php artisan test tests/Feature/SuiviP3Test.php tests/Feature/ApiTest.php
```

## Fichiers sources

| Fichier | Rôle |
|---|---|
| `app/Http/Controllers/Api/V1/SuiviApiController.php` | Contrôleur |
| `app/Http/Controllers/Api/V1/AuthApiController.php` | Scope `suivi` |
| `routes/api.php` | Déclaration routes |

## Endpoints Suivi-Évaluation (P3)

### GET `/api/v1/suivi-evaluation/synthese`

Paramètre **obligatoire** : `exercice` (ID ou année).

```json
{
  "data": {
    "exercice_id": 1,
    "scores": { "score_global": 62.0, "score_physique": 45.0 },
    "niveau": "partiel",
    "recommandations": { "total": 8, "realisees": 2 },
    "missions": { "total": 1, "en_cours": 1 },
    "plans": { "total": 1, "en_cours": 1 }
  }
}
```

### GET `/api/v1/suivi-evaluation/evaluations`

Liste des snapshots `evaluations_periodiques`.

### GET `/api/v1/suivi-evaluation/missions`

Missions S&E de l'exercice.

### GET `/api/v1/suivi-evaluation/plans`

Plans d'amélioration avec compteur d'actions.

**Permission web** : `suivi_evaluation.view` (ou alias `suivi.voir` / `recommandation.voir` / `rapport.voir`).
| `database/seeders/ProductionPermissionsSeeder.php` | Permissions prod |
| `app/Console/Commands/SyncProductionPermissions.php` | Commande sync |
