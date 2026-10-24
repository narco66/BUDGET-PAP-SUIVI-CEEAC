# 06 — Audit LIQ

## Conforme

- Quota cumul ≤ engagé, parent visé, workflow CF, pièces.
- Service fait obligatoire avant visa CF ; circuit chef de service puis CF.
- ORD auto idempotent au visa LIQ.
- Liquidations partielles : le brouillon auto issu du visa ENG est réemployé (montant de tranche) ; une nouvelle LIQ peut couvrir le reliquat.
- Alerte de facture en doublon (numéro) : exposée en API, **sans blocage**.

## Incomplet

- Retenues fiscales / brut-net : saisie manuelle possible (`montant_brut` − `montant_retenues` = `montant_net`) ; **aucun barème n’est appliqué**.
- UI Figma absente.

## Blocage

- Règles fiscales / retenues : point 5 de `00_LIRE_AVANT_UTILISATION.md`. Structure de champs prévue ; calculs fiscaux **non activés** sans barème.
