# Audit intégration Nomenclature Budgétaire — TB-PAPA-CEEAC

**Date :** juin 2026  
**Périmètre :** référentiel budgétaire, imputation, contrôle des crédits, chaîne de la dépense, RBM/GAR, suivi financier, tableaux de bord, rapports, sécurité, traçabilité.

---

## 1. Synthèse exécutive

La nomenclature budgétaire CEEAC (Titre → Article → Paragraphe → Rubrique → Ligne budgétaire) est **implémentée et opérationnelle** comme socle de l'imputation et du contrôle des crédits. La chaîne de la dépense est **fonctionnelle** pour le cas nominal.

Des **écarts d'intégration** ont été identifiés entre modules (RBM↔Budget, besoin→engagement, reporting dual, périmètres utilisateur). Des **correctifs incrémentaux** ont été appliqués (voir §10) sans modification destructive du schéma ni des données existantes.

**Commande de diagnostic :**
```bash
php artisan budget:audit
php artisan budget:audit --exercice=2026 --json
```

---

## 2. Référentiel budgétaire

| Niveau | Table | Unicité code |
|--------|-------|--------------|
| Titre | `budget_titres` | `(sens, code)` |
| Article | `budget_articles` | `(titre_id, code)` |
| Paragraphe | `budget_paragraphes` | `(article_id, code)` |
| Rubrique | `budget_rubriques` | `(paragraphe_id, code)` |
| Ligne budgétaire | `lignes_budgetaires` | `(exercice_id, code)` |

**Anomalies :** import Excel limité recette/dépense ; résolution rubrique par code seul dans certains imports ; pas de contrainte SQL sur invariants montants (contrôlé en applicatif).

---

## 3. Imputation budgétaire

| Module | Rattachement ligne |
|--------|-------------------|
| Engagement | `engagement_imputations` (obligatoire) |
| Besoin | `ligne_budgetaire_id` + héritage depuis activité (**corrigé**) |
| Aval dépense | Héritage engagement |

**Correctifs :** validation exercice imputation ; pré-remplissage conversion besoin→engagement.

---

## 4. Contrôle des crédits

```
Disponible = COALESCE(montant_revise, montant_ouvert) − montant_engage
Invariants : payé ≤ ordonnancé ≤ liquidé ≤ engagé ≤ alloué
```

**Correctifs :** colonne `montant_revise` ; garde modification crédit ; compteurs prorata dans `ConcerneDepense`.

---

## 5. Chaîne de la dépense

Workflow complet BESOIN → ENGAGEMENT → LIQUIDATION → ORDONNANCEMENT → PAIEMENT.

**Correctif :** annulation engagement bloquée si liquidations actives.

**Reste à faire :** contrainte cumul liquidations ≤ montant engagement ; vue dossier pièces unifiée.

---

## 6. Intégration RBM/GAR

**Correctifs :** `RbmBudgetSyncService` (sync Activité ↔ Ligne) ; champ activité dans formulaire budget.

**Recommandé :** rapprochement imports PAP/lignes ; taux financier depuis exécution réelle.

---

## 7. Suivi financier

Double stack `/rapports` (legacy) vs `/reporting` (moderne). Source unique des montants : `lignes_budgetaires`.

**Correctif :** `executionBudgetaire` utilise crédit alloué (révisé).

---

## 8. Sécurité

**Correctifs :** périmètre structure dans `LigneBudgetairePolicy` ; filtre `AccessScopeService` sur listes budget/engagement.

---

## 9. Traçabilité

`journaux_audit` + `rbm_historiques` + `AuditLogger` (imports, engagements, liaisons RBM).

---

## 10. Fichiers correctifs (juin 2026)

- `database/migrations/2026_06_19_000001_renforcement_nomenclature_budgetaire.php`
- `app/Services/Budget/RbmBudgetSyncService.php`
- `app/Services/Budget/BudgetAuditService.php`
- `app/Console/Commands/BudgetAuditCommand.php`
- `app/Models/Concerns/ConcerneDepense.php`
- `app/Http/Requests/EngagementRequest.php`
- `app/Http/Requests/LigneBudgetaireRequest.php`
- `app/Policies/LigneBudgetairePolicy.php`
- `tests/Feature/BudgetNomenclatureAuditTest.php`

---

## 11. Plan de consolidation

1. ~~Contrainte liquidations cumulées~~ — **Fait** (`DepenseQuotaService`)
2. ~~Unifier reporting~~ — **Fait** (redirection `/rapports` → `/reporting`, type `execution_synthese`)
3. ~~Commande réconciliation~~ — **Fait** (`php artisan budget:reconcilier`)
4. ~~Rapprochement imports PAP ↔ lignes~~ — **Fait** (`PapBudgetRapprochementService`, `budget:rapprocher-pap`, UI import budget)
5. ~~Taux financier PAP depuis exécution réelle~~ — **Fait** (`PapPerformanceService`, sync auto au paiement, `pap:sync-taux-financiers`)
6. ~~Audit sémantique des mouvements de compteurs~~ — **Fait** (`CompteurMouvementAuditService`, `budget:audit-compteurs`, section `mouvements_compteurs` dans `budget:audit`)

---

## 12. Consolidation référentiel financier (phases A→D, juin 2026)

| Phase | Périmètre | Statut |
|-------|-----------|--------|
| **A** | Exercices budgétaires (`ExerciceService`, verrouillage transversal) | ✅ |
| **B** | Sources, PTF, conventions, ventilation multi-sources | ✅ |
| **C** | Contrôles SQL, middleware exercice ouvert, `NomenclatureResolver`, audit enrichi | ✅ |
| **D** | Hub `/referentiel-financier`, exports Excel/PDF, documentation | ✅ |

**Hub :** `GET /referentiel-financier` — indicateurs, liens CRUD, exports, alertes audit.

**Documentation :** `docs/referentiel-financier.md`

**Tests :**
```bash
php artisan test --filter=ExerciceReferentielTest
php artisan test --filter=ReferentielFinancierTest
php artisan test --filter=ControlesBudgetPhaseCTest
php artisan test --filter=ReferentielFinancierPhaseDTest
```

**Fichiers Phase D :**
- `app/Services/Budget/ReferentielFinancierService.php`
- `app/Http/Controllers/ReferentielFinancierController.php`
- `app/Http/Controllers/ReferentielFinancierExportController.php`
- `app/Exports/Referentiel/*`
- `resources/views/referentiel/hub.blade.php`
- `resources/views/referentiel/pdf/*`
