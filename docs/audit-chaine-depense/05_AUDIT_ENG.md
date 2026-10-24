# 05 — Audit ENG

## Conforme

- Réservation `lockForUpdate`, multi-imputations, refus de dépassement, idempotence soumission, compteurs ligne.

## Incomplet

- Lien EB (admin peut créer sans EB), PDF.

## Absent

- Identité structurelle du Directeur du Budget (mapping rôle `directeur`).

## Corrections amorcées

- Circuit `ENGAGEMENT` : Direction du Budget (`directeur`, mapping configurable) puis Contrôleur financier.
- LIQ automatique après visa ENG (`EngagementVersLiquidationService`, idempotent).
- Insuffisance de crédits : statut `en_attente_arbitrage` + `diagnostic_credits` (aucun virement ni crédit créé). Reprise après une opération budgétaire régulière (ex. hausse du montant ouvert) : le diagnostic est effacé à la réservation.
- Dégagement plafonné au reliquat non liquidé (`EngagementService::degager`) ; l’annulation totale est refusée dès qu’une LIQ visée existe.
- `expression_besoin_id` obligatoire y compris pour `depenses.admin`. Hors PAP : pas d’activité PAP fictive.

## Décision bloquée

- Identité du « Directeur du Budget » : rôle `directeur` + contrôle de structure **si** `structure_id` est renseigné (`codes_structure_directeur_budget`, défaut `DIR-BUDGET`). Structure nulle : rôle seul (tests). Mapping institutionnel encore à valider.
