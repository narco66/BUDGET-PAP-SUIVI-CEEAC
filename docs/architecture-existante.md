# Diagnostic de l'architecture existante

Date d’audit : 2026-10-23. Périmètre : dépôt `BUDGET-PAP-SUIVI-CEEAC`.

## Faits vérifiés

| Élément | Constat |
|---|---|
| Framework backend | Laravel **13.8** (`composer.json`), PHP **^8.3** |
| Présentation | **Blade** (~400 vues), **Alpine.js**, **Bootstrap 5** (CDN), Vite + un peu de Tailwind en toolchain |
| SPA séparé | Répertoire `/frontend` (Vue 3 + TypeScript) — parcours auth, tableau de bord, exercices, structures |
| API | `routes/api.php`, préfixe **`/api/v1`**, 13 contrôleurs `App\Http\Controllers\Api\V1` |
| Auth API | Jetons **Sanctum PAT** (`POST /api/v1/token`), scopes `lecture`, `suivi`, `tompro` |
| Auth web | Session Laravel, cookies, MFA TOTP (`pragmarx/google2fa`), anti brute-force IP |
| Autorisations | **spatie/laravel-permission** v8, ~36 Policies, alias de permissions, `UserAccessScope` |
| Métier | Couche **Services** déjà dense (~188 classes) : budget, dépense, PAP, RBM, GED, workflow, reporting |
| Données | Migrations Eloquent, **SQLite** en local (`.env.example`), **MySQL** prévu en production |
| Documents | DomPDF, PhpWord, PhpPresentation, Maatwebsite Excel |
| Temps réel | Laravel Reverb optionnel, Echo + Pusher JS |
| OpenAPI | `resources/openapi/v1.json` (catalogue, schémas encore partiels) + Swagger UI web (`api.docs`) |
| Tests | PHPUnit 12, suites Feature/Unit |

## Cartographie des dépendances

```
Navigateur  →  routes/web.php  →  Contrôleurs HTTP  →  Services métier  →  Eloquent / MySQL
                    │                                         ▲
                    └── Blade / Alpine                        │
                                                              │
Clients SI (TOMPRO, webhooks) → /api/v1 (Bearer) ─────────────┘
```

Les règles budgétaires **ne sont pas** dans les vues de façon exclusive : une grande partie vit déjà dans `app/Services`. Les contrôleurs web restent toutefois des orchestrateurs qui rendent des vues plutôt que des JSON.

## Modules réellement présents (inventaire)

Référentiels et structures, exercices, nomenclature, programmation/révisions budgétaires, PAP et chaîne de résultats (Pilier → Tâche), indicateurs, expression des besoins, marchés/contrats, chaîne de la dépense (engagement → paiement), recettes, projets/stratégie, GED, tableaux de bord/reporting, administration, workflow/visas, audit, interopérabilité.

Organisation utilisée : **Service → Direction → Département** (lineage dans `StructureResource`).

## Défauts préexistants (non introduits par la migration)

- OpenAPI v1 largement **déclaratif** (peu de schémas de réponse).
- Plusieurs montants API encore sérialisés en **float** JSON (`LigneBudgetaireResource`, `EngagementResource`).
- Certains endpoints API authentifiés **sans** middleware `ability:lecture` (suivi, workflow, reporting).
- README racine encore celui du squelette Laravel, pas du produit CEEAC.

## Hypothèses

- Le déploiement cible du SPA et de l’API pourra être **sur des origines distinctes** (CORS + cookies).
- Les compétences équipe sont plus proches de **Laravel / Blade / Alpine** que de React ; Vue 3 est le choix de transition.

## Informations manquantes

- URL et certificats HTTPS de production, domaines exacts frontend/backend.
- Décision d’hébergement du SPA (Laragon, reverse-proxy nginx, CDN).
- Calendrier de bascule et politique de conservation de l’UI Blade.
