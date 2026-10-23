# Module « Structure du Budget » — BUDGET-PAP-SUIVI-CEEAC

Couche de **restitution unifiée**, **saisie**, **contrôle** et **validation** au-dessus de l'architecture budgétaire fédérée. **Non destructif** : pas de table centrale ; agrégation via services sur `structures`, `lignes_budgetaires`, RBM et nomenclature.

**Conformité post-lots 1→7 : ~95 %** (audit initial ~78 %).

---

## 1. Architecture fédérée

| Couche | Tables / modules |
|--------|------------------|
| Institutionnel | `structures` (parent_id, types) |
| Programmatique | Pilier → Axe → Produit → SousProduit → Activité |
| Budgétaire | `lignes_budgetaires` (hub), `budgets` (enveloppe) |
| Nomenclature | Titre → Article → Paragraphe → Rubrique |
| Financement | `sources_financement`, pivot multi-sources |

**Service central :** `App\Services\Budget\StructureBudgetService`

---

## 2. Lots livrés

### Lot 1 — CRUD in-module & policy
- `StructureBudgetPolicy`, gates `structureBudget.*Ligne`
- `StructureBudgetLigneRequest`, fiche / edit / archive / delete
- `StructureBudgetExport` (Excel)

### Lot 2 — Arbre complet & recherche
- `arbreComplet()`, `rechercher()`, vues arbre-complet / recherche / fiche

### Lot 3 — Wizard multi-étapes
- `StructureBudgetWizardService`, 6 étapes, endpoints AJAX

### Lot 4 — Import / export consolidés
- Hub `import-export`, xlsx/csv/pdf, import dry-run + confirmation

### Lot 5 — Intégrations & cohérence
- `RbmBudgetSyncObserver`, `StructureBudgetCoherenceService`
- Tableau de bord enrichi (anomalies, N/N-1, écarts PAP, réconciliation)
- `ligne_budgetaire_id` sur recettes / prévisions

### Lot 6 — Validation, API, journal
- `statut_validation` : brouillon → soumis → valide / rejete
- `StructureBudgetValidationService`, journal MOA
- API v1 : synthese, arbre, lignes, workflow POST

### Lot 7 — Documentation & recette 2026
- `StructureBudgetRecetteService` + `php artisan structure-budget:recette-2026`
- `tests/Feature/StructureBudgetRecette2026Test.php`
- Manuel utilisateur, grille recette, PV, checklist animateur

---

## 3. Routes principales

| Route | Nom | Description |
|-------|-----|-------------|
| GET `/structure-budget` | `structure-budget.arbre` | Arbre rollup |
| GET `/structure-budget/arbre-complet` | `structure-budget.arbre-complet` | Arbre + PAP |
| GET `/structure-budget/recherche` | `structure-budget.recherche` | Recherche multicritère |
| GET `/structure-budget/tableau-bord` | `structure-budget.tableau` | Dashboard cohérence |
| GET `/structure-budget/wizard` | `structure-budget.wizard` | Assistant saisie |
| GET `/structure-budget/import-export` | `structure-budget.import-export` | Hub I/E |
| GET `/structure-budget/journal` | `structure-budget.journal` | Journal audit |
| POST `/structure-budget/lignes/{ligne}/soumettre` | `structure-budget.ligne.soumettre` | Workflow |
| GET `/api/v1/structure-budget/synthese` | `api.structure-budget.synthese` | API REST |

Liste complète : `routes/web.php` (prefix `structure-budget`), `routes/api.php`.

---

## 4. Permissions (`structure_budget.*`)

Catalogue : `RoleSeeder` — voir, creer, modifier, supprimer, valider, rejeter, exporter, importer, consulter_journal.

| Rôle | Droits clés |
|------|-------------|
| Gestionnaire budgétaire | creer, modifier, exporter, importer |
| Directeur | valider, rejeter, consulter_journal |
| Auditeur | consulter_journal (+ audit.voir) |
| Lecteur | voir uniquement |

---

## 5. Services

| Service | Rôle |
|---------|------|
| `StructureBudgetService` | Arbre, rollup, synthèse, recherche |
| `StructureBudgetWizardService` | Assistant saisie |
| `StructureBudgetCoherenceService` | Anomalies, évolution, écarts PAP |
| `StructureBudgetValidationService` | Workflow lignes |
| `StructureBudgetJournalService` | Consultation journal |
| `StructureBudgetRecetteService` | Contrôles recette MOA 2026 |
| `RbmBudgetSyncService` | Liaison Activité ↔ Ligne |

---

## 6. Tests

```bash
php artisan test --filter=StructureBudget
php artisan test --filter=StructureBudgetRecette2026
php artisan structure-budget:recette-2026
```

| Fichier | Couverture |
|---------|------------|
| `StructureBudgetTest.php` | 33 tests — lots 1→6 |
| `StructureBudgetRecette2026Test.php` | 3 tests — recette Budget 2026 |

---

## 7. Documentation MOA

| Fichier | Usage |
|---------|-------|
| `docs/manuel-utilisateur-structure-budget.md` | Guide utilisateur |
| `docs/recette-structure-budget-2026.md` | Scénarios recette |
| `docs/pv-recette-structure-budget-2026.md` | Modèle PV |
| `docs/checklist-animateur-PV-REC-SB-2026-01.md` | Checklist jour J |

---

## 8. Budget 2026 (recette)

- **Seeder :** `Budget2026Seeder` — 35 lignes, total **26 275 514 803 XAF**
- Lignes en statut **valide** (budget adopté Malabo)
- Contrôle automatisé : `StructureBudgetRecetteService::TOTAL_OUVERT_2026`

---

## 9. Choix d'architecture (maintenus)

- Pas de table `structure_budgetaire` — zéro redondance.
- Programme restitué via **Pilier** RBM ; `programmes` = pont référentiel.
- Saisie des niveaux supérieurs (structure, RBM) via leurs CRUD dédiés.
