# Audit des calculs budgétaires — Exercice 2026

**Date :** 25 octobre 2026 · **Script de recalcul :** `database/scripts/audit_calculs_budgetaires.php` (lecture seule) · **Résultats :** `docs/audits/donnees/calculs.json` (avant correction), `calculs_apres.json` (après).

## 1. Règles appliquées (déduites du code, non d'une formule générique)

| Agrégat | Règle | Source |
|---|---|---|
| Crédit alloué | révisé s'il est > 0, sinon ouvert | `LigneBudgetaire::montant_alloue` |
| Engagé | Σ imputations des engagements à crédits réservés (non supprimés) | `Engagement::reserverCredits`, `libererCredits`, `EngagementService::degager` |
| Liquidé / ordonnancé / payé | montant de l'étape (paiement en XAF) appliqué une fois (`compteurs_appliques`), réparti au prorata des imputations de l'engagement | `ConcerneDepense::appliquerCompteur`, `DepenseCompteurService::repartir` |
| Payé (états) | paiements **réglés** : validés et instrument non rejeté par la banque | `Paiement::regles()` (ajouté) |
| Disponible à engager | alloué − engagé | `LigneBudgetaire::disponible` |
| Reste à payer | ordonnancé − payé | `SuiviFinancierCalculator` |
| Taux | engagement = engagé / alloué ; liquidation = liquidé / engagé ; ordonnancement = ordonnancé / liquidé ; paiement = payé / ordonnancé ; exécution = payé / alloué | `SuiviFinancierCalculator::synthese` |

Crédit disponible, crédit non engagé et trésorerie ne sont pas confondus : la trésorerie n'est pas calculée par le module budgétaire.

## 2. Recalcul indépendant

| Agrégat | Stocké (Σ 50 lignes) | Recalculé depuis les dossiers | Écart |
|---|---|---|---|
| Engagé | 68 311 725,00 | 68 311 725,00 | 0,00 |
| Liquidé | 51 466 725,00 | 51 466 725,00 | 0,00 |
| Ordonnancé | 41 431 725,00 | 41 431 725,00 | 0,00 |
| Payé | 28 581 725,00 | 28 581 725,00 | 0,00 |

- **0 écart ligne par ligne** sur les 50 lignes et les 4 compteurs.
- Aucun dépassement : engagé ≤ alloué sur toutes les lignes.
- Ordre de la chaîne respecté : payé ≤ ordonnancé ≤ liquidé ≤ engagé.

## 3. Multiplications par jointure (§ 8.3)

| Requête | Constat | Correction |
|---|---|---|
| `TableauBordService` (exécution, nature, structure) | somme directe des lignes, sans jointure aux dossiers | conforme |
| `ReportingService::global`, `parCategorie`, `parStructure` | idem | conforme |
| `DepenseReportingService::executionParTache` | jointure paiements → ORD → LIQ : liquidé et ordonnancé **multipliés par le nombre de paiements partiels** ; LIQ/ORD validés non payés ignorés | une agrégation par étape (test `AuditDonneesCorrectionsTest::test_execution_par_tache_sans_multiplication…`) |
| `DepenseReportingService::ventilerParTache` | jointure engagement → EB (1:1, index unique) | conforme |
| `ReportingService::parAxe` | rattachement par `activite_id` seulement : toutes les lignes (enveloppes partagées) en « Hors PAP » | rattachement des enveloppes « ACT-{axe} » à leur axe, chaque ligne comptée une fois (Σ axes + reliquat = global au centime) |

## 4. Précision monétaire

- Montants en colonnes `decimal(…, 2)`.
- Les répartitions sont arrondies à 2 décimales, le dernier élément absorbant l'écart d'arrondi (`DepenseCompteurService::repartir`).
- Affichage en XAF entiers, format français ; formats courts (« 40,3 Md ») avec le montant exact en info-bulle.
- Taux arrondis à 1 décimale, séparés des montants.

## 5. Points relevés sur les données officielles (non modifiées)

- Budget 2026 : dépenses 40 305 795 803 XAF ; recettes prévues 40 305 595 800 XAF ; **déséquilibre de 200 003 XAF** à soumettre à la Direction du Budget.
- 3 lignes de personnel ont des montants non arrondis au millier (LB-2026-661, 663, 664) : ils sont conformes au document importé.
