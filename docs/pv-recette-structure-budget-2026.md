# Procès-verbal de recette MOA
## Module Structure du Budget — BUDGET-PAP-SUIVI-CEEAC

**Document type :** Procès-verbal de recette fonctionnelle  
**Version :** 1.0 — Juin 2026  
**Référence PV :** PV-REC-SB-2026-01

---

| **Date de recette** | [JJ/MM/2026] |
|---------------------|--------------|
| **Lieu** | Siège CEEAC — Libreville — [Salle] |
| **Exercice testé** | 2026 |
| **Version applicative** | [ex. commit / tag] |
| **Environnement** | ☐ Préproduction   ☐ Production |

---

## 1. Objet

Le présent procès-verbal atteste de la recette fonctionnelle du **module Structure du Budget**, permettant :

- la restitution unifiée de la structure budgétaire (arbre, rollup, arbre complet PAP) ;
- la saisie assistée, l'import/export consolidé ;
- les contrôles de cohérence (PAP, nomenclature, liaisons RBM) ;
- le workflow de validation des lignes et le journal d'audit ;
- l'accès API REST v1.

**Total Budget 2026 contrôlé :** 26 275 514 803 XAF (35 lignes CEEAC-EM).

---

## 2. Commission de recette

| Rôle | Nom | Fonction | Signature |
|------|-----|----------|-----------|
| Président MOA | Paul-André **MBENG MBA** | Contrôleur financier | |
| Membre | Grace **ONDO** | Gestionnaire budgétaire | |
| Membre | [Directeur structure] | Directeur | |
| MOE | Arsène **BIKANG** | Intégrateur DSI | |
| Secrétariat | Chantal **MBOUMBA** | Assistante | |

---

## 3. Résultats contrôle automatisé

```bash
php artisan structure-budget:recette-2026
```

| Code | Libellé | Résultat |
|------|---------|----------|
| TOTAL_OUVERT_2026 | Total crédits Malabo | ☐ OK ☐ KO |
| NB_LIGNES_2026 | Jeu 35 lignes | ☐ OK ☐ KO |
| RATTACHEMENT_REF | Structure + rubrique | ☐ OK ☐ KO |
| ROLLUP_ARBRE | Consolidation arbre | ☐ OK ☐ KO |
| LIAISON_RBM | Sync activité ↔ ligne | ☐ OK ☐ KO |
| PERMISSIONS | Catalogue permissions | ☐ OK ☐ KO |
| ROUTES | Routes UI + API | ☐ OK ☐ KO |

**Score :** ___ / 7

---

## 4. Scénarios manuels (grille REC-SB-2026-01)

| ID | Scénario | Résultat | C / NC / NA |
|----|----------|----------|-------------|
| C1 | Arbre consolidé 2026 | | ☐ |
| C2 | Tableau de bord cohérence | | ☐ |
| C3 | Fiche ligne LB-2026-661 | | ☐ |
| C4 | Wizard création brouillon | | ☐ |
| C5 | Validation directeur | | ☐ |
| C6 | Rejet avec motif | | ☐ |
| C7 | Permissions lecteur | | ☐ |
| C8 | Export Excel | | ☐ |
| C9 | Journal auditeur | | ☐ |
| C10 | API synthese JSON | | ☐ |

**Taux de succès :** ___ % (seuil ≥ 95 %)

---

## 5. Décision

☐ **Recette acceptée** sans réserve  
☐ **Recette acceptée** avec réserves (liste en annexe)  
☐ **Recette refusée** — levée des réserves avant nouvelle session

**Observations :**

_[À compléter]_

---

## 6. Annexes

- A — Captures écran arbre / tableau / fiche ligne  
- B — Export `structure-budget:recette-2026` (log terminal)  
- C — Rapport `php artisan test --filter=StructureBudget`  
- D — `docs/recette-structure-budget-2026.md`

**Fait à Libreville, le [date].**

Signatures des membres de la commission ci-dessus.
