# Installation et exécution locale

## Prérequis

- PHP 8.3+, Composer, Node.js 20+
- SQLite (défaut `.env.example`) ou MySQL

## Backend (racine du dépôt)

```bash
composer install
copy .env.example .env   # Windows ; cp sous Unix
php artisan key:generate
php artisan migrate
php artisan db:seed      # selon l’environnement de démo
```

Lancer l’API / l’UI Blade :

```bash
php artisan serve --host=127.0.0.1 --port=8000
# Si le port 8000 est occupé : --port=8010 et VITE_DEV_PROXY_TARGET=http://127.0.0.1:8010
```

Sous Laragon, le vhost du dossier `www` peut remplacer `artisan serve`. Dans ce cas, renseigner `VITE_DEV_PROXY_TARGET` (frontend) et `APP_URL`.

## Frontend autonome (`/frontend`)

```bash
cd frontend
copy .env.example .env
npm install
npm run dev
```

L’interface est servie sur `http://localhost:5173`. En développement, Vite proxifie `/api` et `/sanctum` vers `http://127.0.0.1:8000` (surcharge : `VITE_DEV_PROXY_TARGET`).

Ne pas lancer en parallèle le Vite de la racine (`npm run dev` Laravel) : les deux utilisent le port 5173.

Build de production du SPA :

```bash
cd frontend
npm run build
```

Les fichiers sont dans `frontend/dist`. Ils doivent être servis par un serveur statique (nginx, CDN) dont l’origine est listée dans `CORS_ALLOWED_ORIGINS`.

## Variables utiles (sans secrets)

Voir `.env.example` (racine) et `frontend/.env.example`.

- `SANCTUM_STATEFUL_DOMAINS` : hôtes du SPA (sans schéma).
- `CORS_ALLOWED_ORIGINS` : origines exactes, jamais `*`.
- `SPA_LEGACY_URL` : URL de l’UI Blade pendant la coexistence.

## Tests backend

```bash
php artisan test --filter=SpaSessionApiTest
php artisan test --filter=MontantTest
php artisan test --filter=DepenseSpaApiTest
php artisan test --filter=DepenseAvalSpaApiTest
php artisan test --filter=RapprochementSpaApiTest
php artisan test --filter=PapSpaApiTest
php artisan test --filter=RecettesSpaApiTest
php artisan test --filter=AppelsFondsSpaApiTest
php artisan test --filter=PlanApurementSpaApiTest
php artisan test --filter=RecettesEtatsSpaApiTest
php artisan test --filter=RbmSpaApiTest
php artisan test --filter=GedSpaApiTest
php artisan test --filter=ReportingSpaApiTest
php artisan test --filter=WorkflowSpaApiTest
php artisan test
```

## Tests frontend

```bash
cd frontend
npm run build
```

Aucune suite e2e navigateur n’est encore branchée dans cet incrément.
