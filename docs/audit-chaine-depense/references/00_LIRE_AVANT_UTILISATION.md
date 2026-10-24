# BUDGET-CEEAC — Dossier complet de la chaîne de la dépense

**Version 1.0 — 24 octobre 2026**

## Contenu

Chaque module dispose d’une spécification autonome **DOCX + MD** :

1. EB — Expression de besoin
2. ENG — Engagement
3. LIQ — Liquidation
4. ORD — Ordonnancement
5. PAY — Paiement

## Chaîne et responsabilité

EB approuvée → création automatique ENG → validation Direction du Budget → visa Contrôle financier → création automatique LIQ → certification du service fait → visa Contrôle financier → création automatique ORD → signature SG (≤ 5 000 000 XAF) ou Président (> 5 000 000 XAF) → transmission Agence comptable → PAY signé par Agent comptable → preuve et rapprochement.

**Attention :** les circuits PAP/hors PAP sont détaillés dans EB. L’assiette du seuil ORD (net à payer dans cette version) doit être validée juridiquement et réglementairement avant déploiement. Les ajustements de crédits n’autorisent jamais le dépassement implicite des enveloppes.

## Règles de gouvernance

- L’approbation est distincte de la comptabilisation financière.
- Tous les montants proviennent de PostgreSQL et sont calculés en décimal exact.
- Aucun montant, utilisateur, crédit, imputation ou statut ne doit être simulé côté interface en production.
- Une transition automatique est idempotente et auditable.
- Le dossier conserve l’historique, les signatures, les justificatifs, le lien aux étapes précédentes et suivantes.
- Les fiches et PDF générés sont à comparer aux modèles officiels des maquettes Figma.

## Points à faire valider avant développement définitif

1. Textes et délégations gouvernant les signatures et le seuil d’ordonnancement.
2. Assiette officielle du seuil (net/brut/cumul) et prévention du fractionnement.
3. Modalités de réservation des crédits et mouvements de régularisation.
4. Valeur probante des signatures électroniques et dispositifs QR.
5. Règles fiscales, retenues et gestion des exercices/pluriannualité.
6. Conformité des documents émis avec les modèles validés.
