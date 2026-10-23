# Module Référentiel Financier — BUDGET-PAP-SUIVI-CEEAC

Hub centralisé du référentiel budgétaire et financier de la CEEAC : exercices, sources de financement,
partenaires (PTF), conventions, nomenclature, lignes budgétaires et contrôles d'intégrité.

## 1. Architecture

```
Exercice budgétaire
    ├── Nomenclature (Titre → Article → Paragraphe → Rubrique)
    │       └── Ligne budgétaire (crédits ouverts / révisés)
    │               └── Ventilation multi-sources (ligne_source_financement)
    ├── Sources de financement (interne CEEAC / externe PTF)
    │       └── Partenaire financier (PTF)
    │               └── Convention de financement (par exercice)
    └── Contrôles d'intégrité (BudgetAuditService)
```

## 2. Hub référentiel

| Route | Nom | Rôle |
|---|---|---|
| GET /referentiel-financier | referentiel.hub | Tableau de bord : indicateurs, liens CRUD, exports |

**Accès :** au moins une permission parmi `exercice.voir`, `source_financement.voir`,
`partenaire_financier.voir`, `nomenclature.voir`, `ligne_budgetaire.voir`.

**Indicateurs affichés :** nombre d'exercices, sources actives, partenaires PTF, lignes budget
(de l'exercice sélectionné), rubriques, lignes multi-sources, anomalies d'audit.

## 3. Sous-modules (routes existantes)

| Module | Préfixe route | Permissions |
|---|---|---|
| Exercices budgétaires | `/exercices` | `exercice.*` |
| Sources de financement | `/sources` | `source_financement.*` |
| Partenaires (PTF) | `/partenaires` | `partenaire_financier.*` |
| Conventions | `/conventions` | `convention_financement.*` |
| Nomenclature | `/nomenclature` | `nomenclature.*` |
| Budget & crédits | `/budget` | `ligne_budgetaire.*` |

## 4. Exports (Phase D)

| Route | Nom | Format | Permission |
|---|---|---|---|
| GET /referentiel-financier/export/sources | referentiel.export.sources | Excel | `source_financement.exporter` |
| GET /referentiel-financier/export/partenaires | referentiel.export.partenaires | Excel | `partenaire_financier.exporter` |
| GET /referentiel-financier/export/conventions | referentiel.export.conventions | Excel | `convention_financement.exporter` |
| GET /referentiel-financier/pdf/synthese | referentiel.pdf.synthese | PDF | `nomenclature.generer_pdf` ou `*.exporter` |
| GET /referentiel-financier/pdf/sources | referentiel.pdf.sources | PDF | idem |
| GET /referentiel-financier/pdf/ptf | referentiel.pdf.ptf | PDF | idem |

Paramètre optionnel `?exercice={id}` pour filtrer conventions et synthèse PDF.

## 5. Exercices budgétaires (Phase A)

Service `ExerciceService` :
- `ouvrir()` / `cloturer()` — cycle de vie
- `definirActif()` — exercice courant unique
- `assertExerciceOuvert()` / `assertReferentielModifiable()` — verrouillage transversal

Middleware `EnsureExerciceOuvert` (alias `exercice.ouvert`) : bloque création/modification budget
et imports sur exercice clos.

## 6. Multi-sources & PTF (Phase B)

- Table pivot `ligne_source_financement` : ventilation CEEAC + PTF par ligne budgétaire
- `LigneFinancementService::synchroniser()` — source principale + répartition
- `ReportingService::parBailleur()` / `parPartenaire()` — agrégats depuis le pivot

## 7. Contrôles & cohérence (Phase C)

- Contraintes SQL crédits (MySQL) : `montant_engage ≤ montant_ouvert`, etc.
- `NomenclatureResolver` — résolution rubrique non ambiguë (paragraphe + code)
- `BudgetAuditService` — sections `exercices`, `referentiel_financier`, `ptf`, `pap_budget`, `anomalies_total`
- Commande : `php artisan budget:audit [--exercice=ANNEE] [--json]`

## 8. Taux financier PAP (exécution réelle)

- Source : `lignes_budgetaires.montant_paye` / `activites.montant_total` (via liaison PAP↔budget)
- `PapPerformanceService::tauxFinancierReel()` — calcul ; `synchroniserTauxFinancier()` — persistance
- Sync automatique à chaque paiement validé (`ConcerneDepense`)
- Taux physique : collectes trimestrielles ; taux financier : exécution budgétaire réelle
- Commande : `php artisan pap:sync-taux-financiers --exercice=ANNEE`

## 9. Fichiers clés

| Fichier | Rôle |
|---|---|
| `app/Services/Budget/ReferentielFinancierService.php` | Indicateurs hub + données export |
| `app/Services/Budget/PapBudgetRapprochementService.php` | Rapprochement PAP ↔ lignes |
| `app/Services/Pap/PapPerformanceService.php` | Taux financier réel + performance PAP |
| `app/Services/Budget/CompteurMouvementAuditService.php` | Audit sémantique compteurs ↔ chaîne dépense |
| `app/Services/Depense/DepenseCompteurService.php` | Répartition prorata (source unique) |
| `app/Http/Controllers/ReferentielFinancierController.php` | Hub |
| `app/Http/Controllers/ReferentielFinancierExportController.php` | Exports Excel/PDF |
| `app/Exports/Referentiel/*` | Classes Maatwebsite Excel |
| `resources/views/referentiel/hub.blade.php` | Vue hub |
| `resources/views/referentiel/pdf/*` | Templates PDF DomPDF |

## 10. Audit sémantique des compteurs (Phase consolidation)

Reconstitution des compteurs `montant_engage / liquide / ordonnance / paye` depuis :
- Engagements avec `credits_reserves`
- Liquidations / ordonnancements / paiements `valide` + `compteurs_appliques`

Détection : étape validée sans compteur, compteur appliqué sans validation, écart ligne vs attendu.

```bash
php artisan budget:audit-compteurs --exercice=2026 [--json]
```

Service partagé : `DepenseCompteurService` (répartition prorata identique à `ConcerneDepense`).

## 11. Tests & commandes

```bash
php artisan budget:audit-compteurs --exercice=2026 [--json]
php artisan budget:rapprocher-pap --exercice=2026 [--dry-run] [--aligner-montants]
php artisan pap:sync-taux-financiers --exercice=2026
php artisan budget:reconcilier --exercice=2026 [--dry-run]
php artisan test --filter=CompteurMouvementAuditTest
php artisan test --filter=PapBudgetRapprochementTest
php artisan test --filter=PapModuleTest
php artisan test --filter=ReferentielFinancierPhaseDTest
php artisan test --filter=ExerciceReferentielTest
php artisan test --filter=ReferentielFinancierTest
php artisan test --filter=ControlesBudgetPhaseCTest
```
