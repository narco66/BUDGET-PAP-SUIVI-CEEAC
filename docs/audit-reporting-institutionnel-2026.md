# Rapport d'audit — Module Reporting Institutionnel

**Application :** BUDGET-PAP-SUIVI-CEEAC  
**Référence audit :** AUD-RPT-2026-01  
**Date :** 13 juin 2026  
**Périmètre :** Module Reporting & Analyse (`/reporting`) et écosystème associé  
**Méthode :** Revue documentaire, analyse code/migrations, exécution tests (`ReportingModuleTest`, `RestitutionTest`, `CockpitActivitesRetardTest`)

---

## Synthèse exécutive

| Indicateur | Valeur |
|------------|--------|
| **Niveau global de conformité** | **~49 %** |
| Rapports opérationnels unifiés | **16 types** via `RapportComposer::TYPES` |
| Tables métier reporting (spec audit) | **1 / 16** présente (`rapports`) |
| Tests automatisés dédiés | **4 fichiers** (~15 scénarios reporting) |
| Formats hub principal | PDF, Excel, Word *(CSV code-only)* |
| Automatisation planifiée | **Absente** |
| Rapports trimestriel / semestriel / annuel institutionnels | **Absents** |
| Rapports IPSAS dédiés | **Absents** |

**Verdict :** le module constitue une **base solide de reporting opérationnel** (source unique `RapportComposer`, réconciliation budgétaire, historisation, signature, QR), mais **ne remplit pas encore** le rôle de **système officiel unique** de production des rapports de gestion institutionnels périodiques (trimestriel, semestriel, annuel), stratégiques consolidés et IPSAS, tel que défini dans le cahier d'audit MOA.

---

## 1. Niveau global de conformité

**Score global : ~49 %** (échelle MOA 0–100 %)

| Domaine | Poids | Score | Contribution |
|---------|-------|-------|--------------|
| Moteur reporting unifié & cohérence données | 20 % | 78 % | 15,6 |
| Rapports budgétaires & financiers | 15 % | 65 % | 9,8 |
| Rapports institutionnels périodiques | 15 % | 10 % | 1,5 |
| Rapports stratégiques (pilier/axe/département) | 12 % | 38 % | 4,6 |
| Schéma de données reporting | 10 % | 6 % | 0,6 |
| Intégrations inter-modules | 15 % | 58 % | 8,7 |
| Tableaux de bord | 8 % | 52 % | 4,2 |
| Formats & diffusion | 5 % | 62 % | 3,1 |
| Automatisation & planification | 5 % | 8 % | 0,4 |
| Sécurité, gouvernance, audit trail | 10 % | 42 % | 4,2 |
| **Total** | **100 %** | — | **~49 %** |

---

## 2. Éléments conformes

### Architecture cœur (Phase 1 — livré)

- **Source unique** : `RapportComposer` → sections normalisées consommées identiquement par PDF (`reporting.pdf.document`), Excel (`RapportExport`) et Word (`WordRenderer`).
- **Règle d'or budgétaire** : montants issus des compteurs `lignes_budgetaires` ; réconciliation Σ(axe) = Σ(catégorie) = Σ(bailleur) = global via ligne « reliquat ».
- **Registre `rapports`** : référence unique, versioning, hash SHA-256, QR token, signature HMAC, archivage disque `storage/app/rapports/{annee}/`.
- **Traçabilité** : trait `Auditable` sur modèle `Rapport`.
- **Vérification publique** : route `GET /verifier/rapport/{token}` sans auth.
- **Hub unifié** : `/reporting`, cockpit exécutif, centre d'exports `/reporting/historique`.
- **Pont legacy** : anciennes routes `/rapports/*` redirigent vers le module unifié (`RapportController` @deprecated).

### 16 rapports opérationnels disponibles

| Clé | Intitulé | Source principale |
|-----|----------|-------------------|
| `pap` | Performance du PAP | PAP / activités / indicateurs |
| `execution_synthese` | Synthèse d'exécution budgétaire | `ReportingService::global` |
| `execution_axe` | Exécution par axe | Lignes → activités → axes |
| `bailleur` | Exécution par bailleur | Sources financement |
| `categorie` | Exécution par catégorie | Nomenclature (Titres) |
| `rbm_gar` | Technico-financier RBM/GAR | Chaîne Pilier→Activité + IOV |
| `marches` | Exécution PPM | Module marchés |
| `contrats` | Registre contrats | Chaîne dépense |
| `recommandations` | Suivi recommandations | Module recommandations |
| `formations` | Ateliers & formations | Module formations |
| `nomenclature` | Nomenclature budgétaire | Référentiel nomenclature |
| `budget_institutionnel` | Rapport budgétaire institutionnel | `BudgetReportService` |
| `situation_budgetaire` | Situation budgétaire consolidée | SF + structures + bailleurs |
| `indicateurs_evolution` | Évolution IOV | `IndicateurPerformanceService` |
| `ecarts_physique_financier` | Écarts phys./fin. | `PapPerformanceService` |
| `suivi_evaluation` | Suivi-évaluation institutionnel | `SuiviEvaluationService` |

### Intégrations partielles conformes

- **Suivi financier** → `situation_budgetaire`, `execution_*`, `ecarts_physique_financier`.
- **Suivi indicateurs** → `indicateurs_evolution` + lien hub IOV.
- **Suivi-évaluation** → `suivi_evaluation`.
- **PAP / RBM** → `pap`, `rbm_gar`.
- **Chaîne dépense** → agrégations engagements→paiements dans rapports contrats et synthèses.
- **GED** → pont `GedRapportBridgeService::indexer()` *(manuel via `POST ged/rapports/{rapport}/indexer`)*.

### Tests automatisés (exécutés OK)

```bash
php artisan test tests/Feature/ReportingModuleTest.php tests/Feature/RestitutionTest.php
# Résultat : 15 tests, 82 assertions — PASS
```

Couverture : hub/cockpit/apercus, génération PDF/Excel/Word, versioning, signature, QR public, réconciliation stricte, protection accès.

---

## 3. Éléments partiellement conformes

| Élément | Constat | Écart |
|---------|---------|-------|
| **Rapports de performance** | KPI PAP, IOV, S&E, recommandations | Pas de rapport « performance institutionnelle » consolidé unique |
| **Situation financière** | Restes à engager/payer, structures, bailleurs | Pas de trésorerie, créances, dettes, recettes consolidées IPSAS |
| **Performance par pilier/axe** | Contenu dans `rbm_gar` et cockpit `parAxe` | Pas de rapports dédiés pilier/axe avec classement/tendances N/N-1 |
| **Performance par département** | Filtre structure sur rapport PAP ; `parStructure` cockpit | Pas de score global département ni rapport dédié |
| **Suivi physique** | Écarts via taux activité PAP | Pas de remontée directe collectes/justificatifs campagnes dans reporting |
| **Permissions** | `rapport.voir`, `rapport.exporter` (+ matrice Spatie complète non utilisée) | Spec audit `reporting.*` (7 permissions) non alignée ; pas de séparation generate/validate/publish |
| **Workflow** | Statuts `genere` / `signe` / `archive` | Pas de validation MOA ni publication officielle |
| **Formats** | PDF, Excel, Word sur hub | CSV implémenté dans `RapportRenderer` mais **non exposé UI** ; pas de PowerPoint |
| **CSV** | Export cockpit activités retard + Recettes/Dépense séparés | Pas CSV sur les 16 rapports institutionnels via UI |
| **Archivage GED** | Indexation manuelle | Pas d'archivage automatique post-génération |
| **Période** | Colonne `periode` en base | Toujours = année exercice ; pas de T1/T2/S1/S2 |
| **Recettes** | Module `RecettesRapportController` (PDF/Excel/CSV) | **Hors hub reporting** — risque de silo |
| **Planification stratégique** | `StrategieRapportController::coherence` | Hors module reporting unifié |
| **IPSAS** | Mention doc « SYSCOHADA/IPSAS » sur ventilation Titres | Aucun état IPSAS, annexe ou rapport conformité |

---

## 4. Éléments absents

### Rapports institutionnels périodiques

- Rapport **trimestriel** automatique (volet stratégique + physique + financier + budgétaire).
- Rapport **semestriel** (comparaison S1/S2, N/N-1).
- Rapport **annuel consolidé** (performance institutionnelle, mise en œuvre PAP, exécution budgétaire, indicateurs, stratégique).
- Sections « réalisations majeures », « difficultés », « recommandations » structurées dans un rapport périodique unique.

### Rapports IPSAS

- États de gestion, rapports financiers IPSAS, annexes, données préparatoires automatiques.

### Rapports stratégiques dédiés

- `performance_pilier`, `performance_axe`, `performance_departement`, `rapport_strategique_consolide`.

### Automatisation

- Génération planifiée (cron trimestriel/semestriel/annuel).
- Envoi / diffusion automatique.
- Commande `ReportingInstitutionnelSeeder` *(référencée cahier audit — **inexistante** dans le dépôt)*.

### Schéma de données (15 tables absentes)

| Table spec audit | Statut |
|------------------|--------|
| `rapports` | ✅ Existe |
| `rapport_categories` | ❌ Absente |
| `rapport_modeles` | ❌ Absente |
| `rapport_generations` | ❌ Absente *(historique dans `rapports`)* |
| `rapports_performance` | ❌ Absente |
| `rapports_trimestriels` | ❌ Absente |
| `rapports_semestriels` | ❌ Absente |
| `rapports_annuels` | ❌ Absente |
| `rapports_budgetaires` | ❌ Absente |
| `rapports_execution_budgetaire` | ❌ Absente |
| `rapports_financiers` | ❌ Absente |
| `rapports_ipsas` | ❌ Absente |
| `rapports_piliers` | ❌ Absente |
| `rapports_axes` | ❌ Absente |
| `rapports_departements` | ❌ Absente |
| `historique_rapports` | ❌ Absente *(versioning via `rapports.version`)* |
| `audit_reporting` | ❌ Absente |

### Tableaux de bord manquants (spec audit)

- Dashboard **stratégique** autonome (pilier/axe/département).
- Dashboard **financier** autonome (trésorerie, recettes/dépenses).
- Dashboard **opérationnel** autonome (tâches, réalisations).
- Volet **risques** exécutif structuré.

### Permissions spec audit (non implémentées sous ce nom)

- `reporting.view`, `reporting.create`, `reporting.generate`, `reporting.validate`, `reporting.publish`, `reporting.export`, `reporting.admin`  
  → Application utilise `rapport.voir` / `rapport.exporter` (+ permissions Spatie générées mais non câblées au workflow).

---

## 5. Incohérences détectées

| # | Type | Description | Gravité |
|---|------|-------------|---------|
| I1 | Gouvernance | **Double écosystème reporting** : hub `/reporting` vs `RecettesRapportController`, `BudgetRapportController`, `StrategieRapportController` — pas de catalogue unique | Majeure |
| I2 | Période | Colonne `periode` ≠ granularité métier (T1, S1…) ; impossible de distinguer deux rapports trimestriels même type | Majeure |
| I3 | UI / code | Format **CSV** supporté par `RapportRenderer` mais absent des boutons `reporting/show.blade.php` | Mineure |
| I4 | Signature | Rapport signé : pas de verrouillage immuable du fichier (regénération possible v2 ; pas d'interdiction UPDATE post-signature) | Majeure |
| I5 | GED | Archivage GED non automatique → risque d'écart entre registre `rapports` et GED officielle | Majeure |
| I6 | Physique | Écarts phys./fin. basés sur **taux activité PAP**, pas sur **collectes campagnes validées** | Majeure |
| I7 | Doc | `module-reporting.md` Phase 1 liste 5 types ; code en expose **16** — documentation obsolète | Mineure |
| I8 | Permissions | Matrice Spatie crée `rapport.valider`, `rapport.generer_pdf`… jamais utilisées par `ReportingController` | Majeure |

*Tests de réconciliation budgétaire : **aucune incohérence** détectée sur jeu démo (Σ ventilations = global).*

---

## 6. Risques de reporting

| Risque | Impact | Probabilité |
|--------|--------|-------------|
| Production manuelle non planifiée des rapports périodiques | Retard transmission instances CEEAC | Élevée |
| Silos Recettes / Stratégie / Reporting | Chiffres différents selon point d'entrée | Moyenne |
| Absence rapports IPSAS | Non-conformité normative externe | Élevée |
| Période non paramétrable | Rapports trimestriels impossibles sans évolution | Élevée |
| GED non synchronisée | Perte traçabilité archivage officiel | Moyenne |

---

## 7. Risques de gouvernance

- Pas de workflow **validation MOA → publication** : signature électronique ≠ validation institutionnelle.
- Pas de rôle distinct **producteur / valideur / publieur** (spec `reporting.validate`, `reporting.publish`).
- Rapports signés modifiables techniquement (nouvelle version sans lien formel avec cycle de validation).
- Absence de registre `audit_reporting` pour journaliser consultations et publications.

---

## 8. Risques de fiabilité

| Point | Évaluation |
|-------|------------|
| Cohérence montants budgétaires | **Forte** (tests réconciliation) |
| Cohérence IOV / S&E dans rapports dédiés | **Bonne** (services sources uniques) |
| Cohérence inter-modules sans tests croisés | **Moyenne** (peu de tests SF→Reporting, SI→Reporting) |
| Détection anomalies statistiques automatique | **Faible** (alertes cockpit basiques uniquement) |
| Doublons rapports | **Maîtrisé** (versioning explicite) |

---

## 9. Recommandations

1. **Établir le hub `/reporting` comme porte unique** — migrer exports Recettes/Stratégie vers `RapportComposer` ou catalogue fédéré.
2. **Introduire paramètre `periode`** (2026-T1, 2026-S1, 2026) sur génération et contrainte d'unicité `(type, exercice_id, periode, format)`.
3. **Créer rapports périodiques institutionnels** : `trimestriel`, `semestriel`, `annuel` composés multi-volets.
4. **Workflow gouvernance** : brouillon → généré → validé MOA → publié ; interdiction modification post-publication.
5. **Aligner permissions** : alias `reporting.*` ↔ `rapport.*` ou renommage contrôlé + policies.
6. **Indexation GED automatique** à chaque génération (ou à validation).
7. **Renforcer lien suivi physique** : sections campagnes/collectes dans `ecarts_physique_financier`.
8. **Mettre à jour** `docs/module-reporting.md` (16 types, Phase 2 lots 1–3, intégrations SF/SI/SE).
9. **Étendre tests** : cohérence hub SF = rapport `situation_budgetaire` ; hub S&E = `suivi_evaluation` ; génération CSV UI.
10. **Planifier automatisation** : commandes Artisan + scheduler trimestriel/semestriel/annuel.

---

## 10. Plan de correction priorisé

### P0 — Fondations gouvernance & cohérence *(4–6 semaines)*

| # | Action | Livrable |
|---|--------|----------|
| P0-1 | Paramètre `periode` + validation obligatoire exercice | Migration + UI filtres |
| P0-2 | Permissions `reporting.*` (alias) + policies `RapportPolicy` | Seeder + contrôleurs |
| P0-3 | Exposer CSV sur hub ; verrou post-signature | UI + règle métier |
| P0-4 | Tests cohérence croisée SF/SI/SE → rapports | 6+ tests Feature |
| P0-5 | Doc technique à jour | `module-reporting.md` v2 |

### P1 — Rapports périodiques institutionnels *(6–8 semaines)*

| # | Action | Livrable |
|---|--------|----------|
| P1-1 | `RapportComposer::trimestriel` | PDF/Excel/Word multi-volets |
| P1-2 | `RapportComposer::semestriel` | Comparaisons S1/S2, N/N-1 |
| P1-3 | `RapportComposer::annuel` | Consolidation performance + PAP + budget + IOV |
| P1-4 | Commandes `reporting:generer-trimestre`, `…-semestre`, `…-annuel` | CLI |
| P1-5 | Tests génération périodique | `ReportingPeriodiqueTest` |

### P2 — Stratégique, GED, catalogue *(6 semaines)*

| # | Action | Livrable |
|---|--------|----------|
| P2-1 | Rapports `performance_pilier`, `performance_axe`, `performance_departement` | 3 types |
| P2-2 | Tables catalogue `rapport_modeles`, `rapport_categories` | Migrations |
| P2-3 | Indexation GED auto + workflow validation MOA | Service + UI |
| P2-4 | Intégration collectes suivi physique | Sections composer |
| P2-5 | Fédération rapports Recettes dans hub | Refactoring controllers |

### P3 — IPSAS, automatisation, audit *(8 semaines)*

| # | Action | Livrable |
|---|--------|----------|
| P3-1 | Module `rapports_ipsas` (données préparatoires) | Rapport + tables |
| P3-2 | Scheduler Laravel (trimestre/semestre/année) | `routes/console.php` |
| P3-3 | Table `audit_reporting` + journal consultations | Migration + middleware |
| P3-4 | API REST catalogue rapports *(optionnel)* | `/api/v1/reporting/*` |
| P3-5 | Pack recette MOA reporting | PV-REC-RPT-2026-01 |

---

## 11. Tables à créer

**Priorité haute (P1–P2)**

```sql
-- Modèle conceptuel recommandé (Laravel migrations)
rapport_categories      -- id, code, libelle, ordre
rapport_modeles         -- id, type, categorie_id, titre, parametres_schema JSON, actif
rapport_generations     -- id, rapport_id FK, statut, planifie_le, execute_le  (ou étendre rapports)
rapports_trimestriels   -- id, exercice_id, trimestre, rapport_id FK, synthese JSON
rapports_semestriels    -- id, exercice_id, semestre, rapport_id FK
rapports_annuels        -- id, exercice_id, rapport_id FK, decision_moa TEXT
audit_reporting         -- id, rapport_id, user_id, action, ip, created_at
```

**Priorité moyenne (P2–P3)**

- `rapports_performance`, `rapports_budgetaires`, `rapports_ipsas`, `rapports_piliers`, `rapports_axes`, `rapports_departements` — ou **vues matérialisées / JSON `parametres`** sur `rapports` si simplification MOA acceptée.

---

## 12. Colonnes à ajouter

**Table `rapports` (existante)**

| Colonne | Type | Motif |
|---------|------|-------|
| `periode_type` | enum annuel/trimestriel/semestriel/mensuel | Granularité |
| `periode_debut` / `periode_fin` | date | Bornes exactes |
| `statut_workflow` | enum brouillon/valide/publie/archive | Gouvernance |
| `valide_par` / `valide_le` | FK user + timestamp | Validation MOA |
| `publie_par` / `publie_le` | FK user + timestamp | Publication |
| `verrouille` | boolean | Immutabilité post-publication |
| `modele_id` | FK nullable | Lien catalogue |
| `checksum_sources` | char(64) | Empreinte données sources au moment T |

---

## 13. Relations à corriger / ajouter

| Relation | Action |
|----------|--------|
| `rapports.exercice_id` | ✅ OK — rendre **NOT NULL** en production |
| `rapports` → `rapport_modeles` | À créer (P2) |
| `rapports` → GED `ged_documents` | Automatiser via `GedRapportBridgeService` post-génération |
| Rapports périodiques → snapshots sources | FK vers `evaluations_periodiques`, `performances_indicateurs`, `situations_budgetaires` |
| Supprimer silos | Unifier clés types dans `RapportComposer::TYPES` |

---

## 14. Interfaces à développer

| Interface | Route proposée | Priorité |
|-----------|----------------|----------|
| Catalogue rapports périodiques | `/reporting/periodiques` | P1 |
| Validation MOA | `POST /reporting/rapports/{id}/valider` | P2 |
| Publication | `POST /reporting/rapports/{id}/publier` | P2 |
| Planification | `/reporting/planification` | P3 |
| Constructeur *(Lot 5 doc)* | `/reporting/modeles` | P3+ |
| Dashboard stratégique | `/reporting/dashboard/strategique` | P2 |
| Dashboard financier intégré recettes | `/reporting/dashboard/financier` | P2 |

---

## 15. Tests à ajouter

| Fichier | Scénarios |
|---------|-----------|
| `ReportingPeriodiqueTest` | trimestriel, semestriel, annuel — structure sections |
| `ReportingCoherenceModulesTest` | SF hub = `situation_budgetaire` ; SI hub = `indicateurs_evolution` ; SE = `suivi_evaluation` |
| `ReportingPermissionsTest` | `reporting.validate`, refus publish sans rôle |
| `ReportingWorkflowTest` | brouillon → validé → publié → verrouillé |
| `ReportingGedBridgeTest` | indexation auto post-génération |
| `ReportingCsvExportTest` | CSV sur 16 types via UI |
| `ReportingIpsasTest` | *(P3)* structure annexes |
| `ReportingSchedulerTest` | *(P3)* commande planifiée mockée |

---

## Annexe A — Cartographie chaîne fonctionnelle

```text
Données opérationnelles          État actuel
───────────────────────          ───────────
Réf. institutionnel      ──────► nomenclature, budget_institutionnel
RBM/GAR                  ──────► rbm_gar, indicateurs_evolution, pap
Planification strat.     ──────► strategie/rapport (HORS hub)
PAP                      ──────► pap, ecarts_physique_financier
Gestion budgétaire       ──────► execution_*, situation_budgetaire
Gestion recettes         ──────► recettes/rapports (HORS hub)
Chaîne dépense           ──────► contrats, execution_synthese
Suivi physique           ──────► indirect (taux PAP)
Suivi financier          ──────► situation_budgetaire, ecarts
Suivi indicateurs        ──────► indicateurs_evolution
Suivi-évaluation         ──────► suivi_evaluation
GED                      ──────► indexation manuelle

Consolidation            ──────► RapportComposer ✅ (par type)
Analyse                  ──────► Cockpit partiel ✅
Indicateurs              ──────► KPI sections ✅
Rapports                 ──────► 16 types ✅ / périodiques ❌
Tableaux de bord         ──────► cockpit exécutif partiel
Aide à la décision       ──────► alertes basiques cockpit
```

---

## Annexe B — Commandes vérifiées

```bash
# Autorisées et fonctionnelles
php artisan migrate
php artisan test tests/Feature/ReportingModuleTest.php

# Référencée cahier audit — NON TROUVÉE
php artisan db:seed --class=ReportingInstitutionnelSeeder
```

---

*Document produit par audit statique + tests — BUDGET-PAP-SUIVI-CEEAC — Juin 2026.*
