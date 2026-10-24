# Bilan de l’incrément (2026-10-23)

## Migrés ou consolidés

- Diagnostic de l’existant et architecture cible documentés.
- Session SPA Sanctum (login, MFA, logout, CSRF, CORS, rate limit `spa-login`).
- Middleware `ability.or.session` étendu aux lectures suivi / reporting / workflow / notifications et aux actions structure budgétaire.
- Représentation décimale des montants (`App\Support\Montant`) sur le tableau de bord et les lignes budgétaires API.
- Frontend Vue 3 : connexion, MFA, déconnexion, tableau de bord, **exercices (CRUD, ouverture, clôture, actif)**, structures (lecture), structure du budget **(dont import/export)**, **enveloppes budgétaires (création, circuit, préparation, simulations, révisions, rapport de cycle)**, **budget et crédits (lignes)**, PAP, **RBM/GAR (lecture et édition des nœuds)**, GED, reporting, **workflow (performance, corbeille, admin circuits)**, **notifications et alertes**, recettes, encaissements, appels de fonds, plans d’apurement, états des recettes, besoins, engagements, liquidations, ordonnancements, paiements, rapprochement bancaire.
- Actions métier : verrou `lockForUpdate`, `Idempotency-Key`, `If-Match`. Soumission d’engagement : réservation de crédits. Liquidation : plafond `DepenseQuotaService`. Validation aval : compteurs `montant_liquide` / `montant_ordonnance` / `montant_paye`.
- OpenAPI v1 enrichi (besoins, engagements, `/depense/{type}`).
- Tests PHPUnit session, CSRF, menu SPA, montants, structure budgétaire, chaîne de la dépense, PAP, recettes, appels de fonds, plans d’apurement, états des recettes, **RBM nœuds**, GED, reporting, workflow, **notifications**.

## Limites

- L’UI Blade reste le canal de téléchargement binaire (Excel, PDF, GED) et de génération des rapports institutionnels. L’aperçu d’import structure budgétaire est JSON.
- OpenAPI : schémas de réponse encore incomplets pour de nombreux GET.
- Pas de campagne e2e navigateur automatisée.
- Déploiement production non effectué (non autorisé).
- Sauvegarde/restauration : procédure écrite, non rejouée dans cet incrément.

## Travaux restants

Voir `docs/plan-migration.md` étapes 7 à 10 et `docs/matrice-suivi-migration.md` lignes `api-seule` / `blade`.
