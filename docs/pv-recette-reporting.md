# Procès-verbal de recette MOA
## Module Reporting institutionnel — BUDGET-PAP-SUIVI-CEEAC

**Document type :** Procès-verbal de recette fonctionnelle  
**Version du modèle :** 1.0 — Juillet 2026  
**Application :** BUDGET-PAP-SUIVI-CEEAC  
**Module recetté :** Reporting & Analyse (P0 → P3)

---

| **Référence PV** | PV-REC-RPT-2026-01 |
|------------------|-------------------|
| **Date de recette** | [JJ/MM/2026] |
| **Lieu** | Siège CEEAC — Libreville |
| **Exercice testé** | 2026 |
| **Environnement** | ☐ Préproduction   ☐ Production |

---

## 1. Objet

Le présent procès-verbal atteste de la recette fonctionnelle du **module Reporting institutionnel** après implémentation du plan de correction post-audit **AUD-RPT-2026-01** (phases P0 à P3).

Le dispositif permet :

- la **génération multi-format** (PDF, Excel, Word, CSV) avec source unique `RapportComposer` ;
- les **rapports périodiques** trimestriel, semestriel et annuel ;
- la **performance stratégique** (pilier, axe, département) et la **fédération Recettes** ;
- le **workflow MOA** (généré → validé → publié) et l'**indexation GED** ;
- l'**état financier IPSAS** (données préparatoires + snapshot `rapports_ipsas`) ;
- la **planification automatique** (scheduler Laravel) ;
- le **journal audit** (`audit_reporting`) et l'**API REST** `/api/v1/reporting/*`.

---

## 2. Périmètre recetté

| Inclus | Exclus |
|--------|--------|
| Hub `/reporting`, 25+ types de rapports | Constructeur personnalisé (Lot 5) |
| Permissions `reporting.*`, verrou signature/publication | Rapports inventaires / immobilisations |
| Périodiques + CLI + scheduler | Migration historique antérieure |
| IPSAS préparatoire + annexes SYSCOHADA | Consolidation comptable TOMPRO complète |
| Audit + API catalogue | |

---

## 3. Scénarios de test

### Bloc A — Fondations (P0)

| # | Scénario | Résultat attendu | C / NC / R |
|---|----------|-------------------|------------|
| A1 | Génération PDF avec période T2 | `periode_type=trimestriel`, checksum enregistré | ☐ |
| A2 | Export CSV | Format `text/csv`, historisé | ☐ |
| A3 | Signature → verrouillage | UPDATE interdit | ☐ |
| A4 | Cohérence SF ↔ `situation_budgetaire` | Taux exécution identique | ☐ |

### Bloc B — Périodiques (P1)

| # | Scénario | Résultat attendu | C / NC / R |
|---|----------|-------------------|------------|
| B1 | Rapport trimestriel T1 | 6+ volets (stratégique, physique, financier…) | ☐ |
| B2 | CLI `reporting:generer-annuel` | Rapport archivé + référence | ☐ |
| B3 | Comparatif S1/S2 (semestriel) | Section comparatif présente | ☐ |

### Bloc C — Stratégique & GED (P2)

| # | Scénario | Résultat attendu | C / NC / R |
|---|----------|-------------------|------------|
| C1 | `performance_pilier` | Tableau classé par pilier | ☐ |
| C2 | Workflow valider → publier | `statut_workflow` + verrou | ☐ |
| C3 | GED auto post-génération | `ged_indexe=true` | ☐ |
| C4 | Recettes dans hub | `recettes_mobilisation` générable | ☐ |
| C5 | Écarts phys./fin. + collectes | KPI campagnes S&E | ☐ |

### Bloc D — IPSAS, scheduler, audit (P3)

| # | Scénario | Résultat attendu | C / NC / R |
|---|----------|-------------------|------------|
| D1 | `ipsas_etat_financier` | 4 annexes + snapshot JSON | ☐ |
| D2 | CLI `reporting:generer-ipsas` | Succès exit 0 | ☐ |
| D3 | Scheduler (cron) | 3 commandes planifiées | ☐ |
| D4 | Page `/reporting/planification` | Statut scheduler + journal | ☐ |
| D5 | Téléchargement → audit | Ligne `download` dans `audit_reporting` | ☐ |
| D6 | API `/api/v1/reporting/catalogue` | JSON catégories + modèles | ☐ |

---

## 4. Tests automatisés

```bash
php artisan migrate
php artisan db:seed --class=ReportingCatalogueSeeder
php artisan permissions:sync-production --force
php artisan test tests/Feature/ReportingModuleTest.php tests/Feature/ReportingP0Test.php tests/Feature/ReportingPeriodiqueTest.php tests/Feature/ReportingP2Test.php tests/Feature/ReportingP3Test.php
```

---

## 5. Décision de recette

| Décision | ☐ Accepté sans réserve   ☐ Accepté avec réserves   ☐ Refusé |
|----------|----------------------------------------------------------------|
| **Réserves / observations** | |
| **Date** | |
| **Présidente de séance MOA** | |
| **Contrôle financier** | |
| **DSI / Exploitation** | |

---

## 6. Références

| Réf. | Document |
|------|----------|
| AUD-RPT-2026-01 | `docs/audit-reporting-institutionnel-2026.md` |
| DOC-RPT | `docs/module-reporting.md` |
| PV-SE | `docs/pv-recette-suivi-evaluation.md` |
| PV-SF | `docs/pv-recette-suivi-financier.md` |
