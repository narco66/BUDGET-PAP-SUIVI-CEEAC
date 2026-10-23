# Checklist animateur — Recette MOA Structure du Budget

**Référence PV :** PV-REC-SB-2026-01  
**Exercice :** 2026  
**URL :** https://budget-pap-preprod.ceeac.int/structure-budget

| Président MOA | Secrétariat | MOE |
|---------------|-------------|-----|
| Paul-André MBENG MBA | Chantal MBOUMBA | Arsène BIKANG |

**Seuil :** ≥ 95 % scénarios C · `structure-budget:recette-2026` → CONFORME

---

## A — Préparation J-3

| ☐ | Tâche | Responsable |
|---|-------|-------------|
| ☐ | Convocation + PV PDF diffusés | C. MBOUMBA |
| ☐ | `php artisan migrate` préprod | A. BIKANG |
| ☐ | Seeders Budget 2026 (Structure, Nomenclature, Budget2026) | A. BIKANG |
| ☐ | `php artisan structure-budget:recette-2026` → 7/7 | A. BIKANG |
| ☐ | `php artisan test --filter=StructureBudget` → 100 % | A. BIKANG |
| ☐ | Comptes test gestionnaire / directeur / lecteur / auditeur | A. BIKANG |

---

## B — Scénarios jour J (voir recette-structure-budget-2026.md)

| ☐ | ID | Durée estimée |
|---|-----|---------------|
| ☐ | C1 Arbre consolidé | 10 min |
| ☐ | C2 Tableau de bord | 10 min |
| ☐ | C3 Fiche LB-2026-661 | 5 min |
| ☐ | C4 Wizard brouillon | 15 min |
| ☐ | C5 Validation | 5 min |
| ☐ | C6 Rejet motif | 10 min |
| ☐ | C7 Lecteur 403 wizard | 5 min |
| ☐ | C8 Export Excel | 5 min |
| ☐ | C9 Journal auditeur | 5 min |
| ☐ | C10 API synthese | 10 min |

---

## C — Clôture

| ☐ | Action |
|---|--------|
| ☐ | Grille §4 PV complétée |
| ☐ | PV signé (4 originaux MOA + 1 MOE) |
| ☐ | Dépôt GED `archives-moa@ceeac.int` |
| ☐ | CR de recette diffusé sous 48 h |
