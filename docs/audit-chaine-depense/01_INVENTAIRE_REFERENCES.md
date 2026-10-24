# 01 — Inventaire et hiérarchie des références

**Date :** 24 octobre 2026

## Hiérarchie appliquée

1. Textes réglementaires / décisions institutionnelles **officiellement applicables** — **non joints** au dépôt ni au dossier procédures (blocage documenté).
2. Nouvelles descriptions détaillées (24/10/2026), copies dans `docs/audit-chaine-depense/references/`.
3. Cahier des charges / manuels internes du dépôt (`docs/module-chaine-depense.md`, audits 2026).
4. Maquettes Figma — **introuvables** dans le dépôt et dans le dossier procédures.
5. Implémentation technique actuelle.

## Registre

| Document | Emplacement réel | Version | Rôle |
|---|---|---|---|
| Lire avant utilisation | `references/00_LIRE_AVANT_UTILISATION.md` (source : Downloads `BUDGET-CEEAC_Procedures_Chaine_Depense`) | 1.0 — 24/10/2026 | Gouvernance de la chaîne, points à faire valider |
| EB | `references/01_EB_Expression_de_Besoin.md` | 1.0 — 24/10/2026 | Spécification cible EB |
| ENG | `references/02_ENG_Engagement.md` | 1.0 — 24/10/2026 | Spécification cible ENG |
| LIQ | `references/03_LIQ_Liquidation.md` | 1.0 — 24/10/2026 | Spécification cible LIQ |
| ORD | `references/04_ORD_Ordonnancement.md` | 1.0 — 24/10/2026 | Spécification cible ORD + seuil |
| PAY | `references/05_PAY_Paiement.md` | 1.0 — 24/10/2026 | Spécification cible PAY |
| Manuel chaîne D0–D5 | `docs/module-chaine-depense.md` | interne | Comportement actuellement documenté pour les utilisateurs |
| Audit juin 2026 | `docs/audit-chaine-depense.md` | 2026-06-14 | Diagnostic historique (référentiel D0–D5, pas les 5 MD) |
| Architecture existante | `docs/architecture-existante.md` | migration SPA | Stack réelle |
| Conventions API | `docs/conventions-api.md` | migration SPA | Contrats REST actuels |
| Cadre organique | `docs/module-cadre-organique-ceeac.md` | interne | Structures CEEAC |
| Nomenclature | `docs/module-nomenclature.md`, `docs/audit-nomenclature-budgetaire.md` | interne | Imputations |
| GED | `docs/module-ged.md` | interne | Pièces et signatures |
| Workflow | `docs/audit-workflow-validation-2026.md` | interne | Circuits Spatie/Workflow |
| Maquettes Figma EB–PAY | — | — | **Absent** |
| Logo officiel CEEAC | assets Blade/PDF layout | non versionné comme asset « officiel validé » | Présentation PDF existante |
| Modèles PDF officiels | — | — | **Absents** (seuls templates DomPDF internes) |
| Textes de délégation d’ordonnancement | — | — | **Absents** (seuil 5 M XAF : hypothèse opérationnelle du dossier ORD, à valider) |

## Conflits documentaires identifiés

| Conflit | Conséquence | Règle proposée (sans inventer le droit) |
|---|---|---|
| Manuel D0–D5 : EB Chef → Directeur → CF ; spec 2026 : PAP Directeur → Commissaire/SG, hors PAP DRHMG → SG → Président | Circuits incompatibles | Implémenter les **deux circuits 2026** comme paramétrage ; conserver l’ancien circuit `BESOIN` jusqu’à bascule testée |
| Manuel : ENG visa CF seul ; spec : Direction du Budget **puis** CF | Étape manquante | Ajouter une étape « Directeur du Budget » **si** le rôle/position est identifiable dans le référentiel (`DIR-BUDGET` existe comme **structure**, pas comme rôle Spatie dédié) — **décision métier requise** pour le mapping rôle |
| Manuel : ORD visa SG uniquement ; spec : SG ≤ 5 M, Président > 5 M | Compétence incomplète | Paramètre versionné + routage ; assiette = **net à payer** (convention du dossier ORD) **bloquée juridiquement** jusqu’à validation |
| Spec : UUID + PostgreSQL ; app : id auto-incrément + SQLite/MySQL | Écart technique | Ne pas migrer les PK en UUID ni la prod vers PG sans décision d’exploitation |
| Spec : React/Tailwind ; app : Vue 3/Bootstrap | Écart technique | Adapter les exigences visuelles à Vue ; ne pas réécrire le frontend |
| Spec : création auto ENG/LIQ/ORD/PAY ; app : transitions **manuelles** (sauf OP à la validation ORD) | Rupture d’intégration | Automatiser avec idempotence après circuits conformes |
| `convertir` API : passe le besoin à `converti` **sans créer** l’engagement | Doublon / oubli possible | Création ENG unique liée, ou suppression de l’action au profit de la génération automatique |

## Points bloqués (aucune règle inventée)

1. Assiette officielle du seuil ORD (net / brut / cumul) — le dossier 2026 dit « net à payer » **à valider**.
2. Valeur probante des signatures électroniques et QR sur fiches.
3. Mapping exact « Directeur du Budget » vs rôle Spatie `directeur`.
4. Identification juridique de la DRHMG dans le référentiel (structure vs rôle).
5. Maquettes Figma et modèles PDF officiels.
