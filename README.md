# GEST-BUDGET-PAP · CEEAC

Application de gestion budgétaire, PAP et suivi de la Commission de la CEEAC.

Le dépôt se transforme progressivement en architecture **frontend autonome + backend Laravel + API `/api/v1`**. L’interface Blade coexiste tant que les parcours SPA ne sont pas à parité.

## Démarrage rapide

Instructions détaillées : [docs/installation-locale.md](docs/installation-locale.md).

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

```bash
cd frontend
copy .env.example .env
npm install
npm run dev
```

SPA : `http://localhost:5173` · API : `http://127.0.0.1:8000/api/v1`

## Documentation

| Document | Contenu |
|---|---|
| [docs/architecture-existante.md](docs/architecture-existante.md) | Audit de l’existant |
| [docs/architecture-cible.md](docs/architecture-cible.md) | Décisions et structure cible |
| [docs/plan-migration.md](docs/plan-migration.md) | Étapes, risques, retour arrière |
| [docs/conventions-api.md](docs/conventions-api.md) | Dates, montants, erreurs, auth |
| [docs/matrice-suivi-migration.md](docs/matrice-suivi-migration.md) | Parité écran / API / tests |
| [docs/procedures-deploiement.md](docs/procedures-deploiement.md) | Sauvegarde, déploiement, rollback |
| [resources/openapi/v1.json](resources/openapi/v1.json) | Contrat OpenAPI v1 |

## Tests

```bash
php artisan test
```
