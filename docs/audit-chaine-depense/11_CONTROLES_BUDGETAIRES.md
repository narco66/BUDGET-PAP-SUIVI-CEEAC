# 11 — Contrôles budgétaires

## Conforme

- Disponible = ouvert/révisé − engagé (et réservations via lock).
- Refus 422 à la soumission ENG.
- Pas de crédit négatif implicite.

## Écarts

- File d’arbitrage métier (virement, crédit complémentaire) : **non inventée**. L’ENG passe en `en_attente_arbitrage` avec diagnostic ; la reprise (`soumettre` de nouveau) exige une opération budgétaire régulière déjà prévue par le module budget. Le diagnostic est effacé à la réservation réussie.
- Alerte d’audit anti-fractionnement ORD (cumul > seuil, pièces toutes ≤ seuil) : journal + API, **sans blocage** (assiette juridique non validée).
- Dégagement ENG : décrémente `montant_engage` si réservé, plafonné au non liquidé. Pas de virement inventé.

## Politique (non inventée)

La réservation définitive reste à l’**engagement** (EB §4.5). Toute autre politique (réservation dès l’EB) est **bloquée** (point 3 du dossier 00).
