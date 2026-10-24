# Architecture cible

Date : 2026-10-23. Décisions prises à partir du code réel (Laravel 13, Blade, services métier, Sanctum).

## Organisation du dépôt

Le backend Laravel **reste à la racine** : déplacer l’arbre existant vers `/backend` casserait Composer, Artisan, les chemins de déploiement Laragon et les tests. Organisation équivalente :

| Chemin | Rôle |
|---|---|
| `/` (Laravel) | Backend, API `/api/v1`, UI Blade héritée pendant la coexistence |
| `/frontend` | Application Vue 3 + TypeScript autonome |
| `/docs` | Diagnostic, contrats, procédures, matrice de migration |
| `/resources/openapi/v1.json` | Contrat OpenAPI aligné sur les routes implémentées |

## Décisions techniques

| Sujet | Choix | Motif |
|---|---|---|
| Découpage | Backend modulaire, pas de microservices | Les règles vivent déjà dans `app/Services` ; un découpage distribué n’apporte rien à ce stade |
| Base de données | SQLite local / MySQL production, inchangé | Aucune nécessité démontrée de changer de moteur |
| Frontend | **Vue 3 + TypeScript + Vite** | Plus proche de l’existant (Blade/Alpine) que React ; TypeScript pour le contrat API |
| Auth SPA | **Session + cookies Sanctum** (`statefulApi`, CSRF) | Application web propriétaire ; JWT non retenu |
| Auth interop | Jetons PAT Sanctum + scopes `lecture`, `suivi`, `tompro` | Conservé pour TOMPRO et clients SI |
| Montants | Chaînes décimales (`App\Support\Montant`) | Évite l’arrondi IEEE-754 dans le navigateur |
| Coexistence | Menu SPA : chemin Vue si migré, sinon URL Blade | Une seule couche métier ; pas de double écriture |

## Flux cible

```
Navigateur SPA (/frontend)
    │  cookie session + X-XSRF-TOKEN (credentials)
    ▼
Laravel /api/v1  →  FormRequest  →  Policy / ability.or.session
                 →  Service métier (inchangé)
                 →  Eloquent / MySQL
```

Les vues Blade restent servies par les routes `web` jusqu’à remplacement vérifié de chaque parcours.

## Topologie de déploiement

- **Développement** : Vite (`localhost:5173`) proxifie `/api` et `/sanctum` vers Laravel (`127.0.0.1:8000`) — même origine apparente, CSRF simple.
- **Production (origines distinctes)** : CORS limité à `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, cookies `Secure` + `SameSite=lax` (même site parent) ou configuration HTTPS explicite. Jamais `Access-Control-Allow-Origin: *` avec credentials.

## Ce que le frontend ne fait pas

- Aucun accès SQL.
- Aucune autorisation définitive : les boutons suivent `permissions`, le backend refuse hors périmètre.
- Aucun secret métier (clés, jetons interop) dans le bundle.
