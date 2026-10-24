# 00 — État initial (Phase A, lecture seule)

**Date :** 24 octobre 2026  
**Dépôt :** `C:\laragon\www\BUDGET-PAP-SUIVI-CEEAC`  
**Branche observée :** `main` (suivi `origin/main`)  
**HEAD :** `9de33b7` — « Avant Tranformation 23-10-26 »  
**Espace de travail :** nombreuses modifications locales non commitées (migration SPA Vue 3 + API `/api/v1`, y compris besoins/engagements/LIQ/ORD/PAY). **Aucune de ces modifications n’a été écrasée.**

## Environnement

| Élément | Constat réel |
|---|---|
| PHP | 8.3.10 |
| Laravel | 13.15.0 (`composer.json` : `^13.8`) |
| Frontend | **Vue 3 + Vite + Pinia + Bootstrap CEEAC**, pas React/Tailwind |
| Tests UI | PHPUnit 12 ; **aucun Playwright** dans le dépôt |
| Base prévue `.env.example` | `DB_CONNECTION=sqlite` (commentaire : production MySQL) |
| PostgreSQL 18 | **absent** de la stack actuelle |
| Sanctum | session SPA + PAT |
| PDF | DomPDF (`barryvdh/laravel-dompdf`) |
| QR | `endroid/qr-code` (MFA / GED, pas systématique sur fiches EB–PAY) |

## Processus actifs

- Serveur de développement SPA observé précédemment sur le port 5175.
- Tests PHPUnit exécutés sur SQLite mémoire (`phpunit.xml`).
- **Aucune** commande `migrate:fresh` / `db:wipe` n’a été lancée dans le cadre de cet audit.

## Protections (non touchées)

Référentiel organique, budget 2026, nomenclature, utilisateurs : **aucune migration destructive, aucun seeder de réécriture, aucun changement de mot de passe**.

## Inventaire technique de la chaîne existante

| Module | Backend | API SPA | UI Vue | UI Blade |
|---|---|---|---|---|
| EB | `ExpressionBesoin`, `BesoinApiController`, circuit `BESOIN` | `/api/v1/besoins*` | `/besoins` | `resources/views/besoins` |
| ENG | `Engagement` + imputations, `EngagementService` | `/api/v1/engagements*` | `/engagements` | `resources/views/engagements` |
| LIQ / ORD / PAY | modèles + `EtapeDepenseApiController` | `/api/v1/depense/{type}` | `/liquidations`, `/ordonnancements`, `/paiements` | `depense/{type}` |
| Rapprochement | `RapprochementBancaireService` | `/api/v1/rapprochements*` | `/rapprochements` | Blade |

## Contradictions d’architecture (à ne pas « corriger » par réécriture globale)

1. Le prompt maître vise **React + Tailwind + PostgreSQL 18**. L’application réelle est **Laravel + Vue 3 + SQLite/MySQL**.  
   **Règle retenue :** conserver la stack existante ; les exigences UI/Figma s’adaptent à Vue/Bootstrap ; PostgreSQL reste une **cible d’exploitation à arbitrer**, pas un prérequis de recette locale.
2. L’audit interne `docs/audit-chaine-depense.md` (14/06/2026) déclarait le module « ~96 % conforme » au **manuel D0–D5**. Les **cinq nouvelles procédures du 24/10/2026** sont un **référentiel cible distinct** et plus exigeant. Elles prévalent sur l’audit de juin pour le diagnostic d’écarts ci-dessous, sans invalider le code déjà testé.

## Décision de méthode

- Phase A : documentation uniquement (`docs/audit-chaine-depense/`).
- Phase B+ : modifications **additives** (migrations compatibles, circuits paramétrables, tests), **sans** `migrate:fresh` sur une base institutionnelle.
