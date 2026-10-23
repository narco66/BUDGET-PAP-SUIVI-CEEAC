# Recette MOA — Module Structure du Budget · Budget 2026

**Référence :** REC-SB-2026-01  
**Exercice :** 2026  
**Total attendu :** 26 275 514 803 XAF (CEEAC-EM, reprise Malabo)  
**Nombre de lignes seed :** 35 (`Budget2026Seeder`)

---

## 1. Prérequis environnement

```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=StructureSeeder
php artisan db:seed --class=ExerciceSeeder
php artisan db:seed --class=SourceFinancementSeeder
php artisan db:seed --class=NomenclatureBudgetaireSeeder
php artisan db:seed --class=Budget2026Seeder
```

**Contrôle automatisé (obligatoire avant PV) :**

```bash
php artisan structure-budget:recette-2026
php artisan test --filter=StructureBudget
```

Seuil : **7/7 contrôles OK** + **100 % tests verts**.

---

## 2. Comptes de test

| Profil | Rôle | Scénarios |
|--------|------|-----------|
| Gestionnaire | `gestionnaire_budgetaire` | C1–C4, C8 |
| Directeur | `directeur` | C5–C6 |
| Lecteur | `lecteur` | C7 |
| Auditeur | `auditeur` | C9 |
| Admin SI | `administrateur` | C10 |

---

## 3. Grille de scénarios

| ID | Scénario | Profil | Résultat attendu | C/NC/NA |
|----|----------|--------|------------------|---------|
| **C1** | Ouvrir `/structure-budget?exercice=2026` — arbre avec montants consolidés | Gestionnaire | Synthèse ≥ 35 lignes, total affiché | ☐ |
| **C2** | Tableau de bord — KPI, évolution, répartition sens | Gestionnaire | Page OK, pas d'erreur PHP | ☐ |
| **C3** | Recherche `LB-2026-661` — fiche ligne personnel | Gestionnaire | Montant 6 499 720 906 XAF | ☐ |
| **C4** | Wizard — créer ligne test `LB-REC-TEST` 100 000 XAF | Gestionnaire | Créée en **brouillon**, visible en recherche | ☐ |
| **C5** | Soumettre C4 → valider | Directeur | Statut **valide**, badge vert | ☐ |
| **C6** | Créer ligne C4-bis, soumettre, **rejeter** avec motif | Directeur | Statut **rejete**, motif affiché | ☐ |
| **C7** | Lecteur : arbre OK, wizard **403** | Lecteur | Lecture seule respectée | ☐ |
| **C8** | Export Excel exercice 2026 | Gestionnaire | Fichier .xlsx téléchargé | ☐ |
| **C9** | Journal — filtrer `structure_budget_ligne_validee` | Auditeur | Entrées visibles | ☐ |
| **C10** | API `GET /api/v1/structure-budget/synthese?exercice={id}` | Admin + token `lecture` | JSON `ouvert` = 26 275 514 803 | ☐ |

---

## 4. Contrôles automatisés (détail)

| Code | Libellé |
|------|---------|
| `TOTAL_OUVERT_2026` | Σ montants ouverts = 26 275 514 803 XAF |
| `NB_LIGNES_2026` | ≥ 35 lignes actives |
| `RATTACHEMENT_REF` | 0 ligne sans structure ou rubrique |
| `ROLLUP_ARBRE` | Σ racines arbre = synthèse globale |
| `LIAISON_RBM` | 0 liaison activité ↔ ligne désync |
| `PERMISSIONS` | 8 permissions clés présentes |
| `ROUTES` | Routes UI + API enregistrées |

---

## 5. Critères d'acceptation MOA

- ≥ **95 %** scénarios **C** (9/10 minimum).
- Aucun **NC** bloquant sur C1, C2, C3, C10.
- Commande `structure-budget:recette-2026` → **CONFORME**.
- PV signé : `docs/pv-recette-structure-budget-2026.md`.

---

## 6. Références

| Document | Usage |
|----------|-------|
| `docs/module-structure-budget.md` | Architecture |
| `docs/manuel-utilisateur-structure-budget.md` | Guide utilisateur |
| `docs/pv-recette-structure-budget-2026.md` | Modèle procès-verbal |
| `docs/checklist-animateur-PV-REC-SB-2026-01.md` | Checklist animateur |
