# Module Reporting & Analyse — BUDGET-PAP-SUIVI-CEEAC

Système centralisé de reporting institutionnel de la Commission de la CEEAC. **Phase 1** :
rapports calculés exclusivement à partir des **données réelles** (chaîne RBM/GAR + chaîne de la
dépense), multi-format (PDF / Excel / Word), historisés, versionnés, signables électroniquement et
vérifiables par QR code.

## 1. Principe de cohérence (règle d'or)

Tous les montants proviennent **exclusivement** des compteurs réels des `lignes_budgetaires`
(ouvert ≥ engagé ≥ liquidé ≥ ordonnancé ≥ payé) et de la chaîne RBM/GAR. Chaque ventilation
(axe, bailleur, catégorie) est **réconciliée au global** par une ligne « reliquat » : la somme du
détail est strictement égale au total de l'exercice (vérifié par test automatisé).

Une **source de données unique** (sections normalisées produites par `RapportComposer`) alimente à
l'identique les trois formats : aucun recalcul propre à un format → **aucun écart possible** entre
le PDF, l'Excel et le Word.

## 2. Rapports disponibles

| Type (clé) | Intitulé | Source de données |
|---|---|---|
| `pap` | Performance du PAP | Activités, indicateurs, mesures, marchés |
| `execution_synthese` | Synthèse d'exécution budgétaire | `ReportingService::global` |
| `execution_axe` | Exécution budgétaire par axe | lignes → activité → axe (+ reliquat) |
| `bailleur` | Exécution par bailleur (CEEAC/PTF) | sources de financement |
| `categorie` | Exécution par catégorie | nomenclature (Titres) |
| `rbm_gar` | Technico-financier RBM/GAR | Chaîne RBM + IOV |
| `marches` | Exécution du PPM | Module marchés |
| `contrats` | Registre des contrats | Chaîne dépense |
| `recommandations` | Suivi des recommandations | Module recommandations |
| `formations` | Ateliers & formations | Module formations |
| `nomenclature` | Nomenclature budgétaire | Référentiel nomenclature |
| `budget_institutionnel` | Rapport budgétaire institutionnel | `BudgetReportService` |
| `situation_budgetaire` | Situation budgétaire consolidée | Suivi financier |
| `indicateurs_evolution` | Évolution IOV | `IndicateurPerformanceService` |
| `ecarts_physique_financier` | Écarts physique / financier | `PapPerformanceService` |
| `suivi_evaluation` | Suivi-évaluation institutionnel | `SuiviEvaluationService` |
| `trimestriel` | Rapport trimestriel institutionnel | `RapportPeriodiqueService` |
| `semestriel` | Rapport semestriel institutionnel | `RapportPeriodiqueService` |
| `annuel` | Rapport annuel institutionnel consolidé | `RapportPeriodiqueService` |

## 3. Architecture

```
app/Models/Rapport.php                       Registre (historisation, versioning, hash, QR, signature)
app/Services/Reporting/
  ReportingService.php                        Agrégations réelles + réconciliation + cockpit
  RapportComposer.php                         Sections normalisées (source unique)
  RapportRenderer.php                         Orchestration : compose → rend → hash → archive → trace
  WordRenderer.php                            Génération .docx (PhpWord)
  QrCodeService.php                           QR data-URI PNG (endroid/qr-code, backend GD)
  SignatureService.php                        Signature HMAC-SHA256 + vérification
  Fmt.php                                     Formatage homogène (money/percent/num/int)
app/Exports/Reporting/
  RapportExport.php / RapportSheet.php        Classeur Excel multi-feuilles (maatwebsite/excel)
app/Http/Controllers/Reporting/
  ReportingController.php                      Hub, cockpit, aperçu, génération, historique, signature
  VerificationController.php                   Vérification publique par QR (sans auth)
resources/views/reporting/                    index, cockpit, show, historique, verification, pdf/document
```

## 4. Routes

| Méthode | URI | Nom | Rôle |
|---|---|---|---|
| GET | /reporting | reporting.index | Hub « Reporting & Analyse » |
| GET | /reporting/cockpit | reporting.cockpit | Tableau de bord exécutif |
| GET | /reporting/historique | reporting.historique | Centre d'exports |
| GET | /reporting/{type} | reporting.show | Aperçu + configuration |
| GET | /reporting/{type}/generer?format=pdf\|excel\|word | reporting.generer | Génère + télécharge + historise |
| GET | /reporting/rapports/{rapport}/telecharger | reporting.telecharger | Re-téléchargement archivé |
| POST | /reporting/rapports/{rapport}/signer | reporting.signer | Signature électronique |
| GET | /verifier/rapport/{token} | verification.rapport | **Public** — vérification QR |

## 5. Sécurité & traçabilité

- **Permissions granulaires (P0)** : `reporting.view`, `reporting.create`, `reporting.generate`, `reporting.validate`, `reporting.publish`, `reporting.export`, `reporting.admin` — alias vers `rapport.*` via `ReportingPermissionAliases`.
- **Compatibilité** : `rapport.voir` / `rapport.exporter` restent supportées.
- **Policy** : `RapportPolicy` (consultation, génération, signature, téléchargement).
- **Période (P0)** : paramètre `periode` — annuel (`2026`), trimestriel (`2026-T1`…`T4`), semestriel (`2026-S1`/`S2`), mensuel (`2026-M01`…`M12`). Service : `RapportPeriodeService`.
- **Verrou post-signature (P0)** : colonne `verrouille` ; rapport signé non modifiable.
- **Empreinte sources (P0)** : `checksum_sources` (SHA-256 des sections composées).
- **Hash** : SHA-256 du fichier stocké dans `rapports.hash`.
- **Signature** : HMAC-SHA256 (référence + hash + signataire + jeton) avec la clé applicative — non
  répudiable, vérifiable sans le fichier original.
- **QR code** : chaque rapport porte un jeton unique ; le QR encode l'URL publique de vérification
  qui affiche référence, empreinte, statut de signature.
- **Versioning** : re-générer un même rapport (type+exercice+période+format) crée une nouvelle ligne
  avec `version` incrémentée — l'historique complet est conservé.
- **Archivage** : fichiers sous `storage/app/rapports/{annee}/`.
- **Audit** : le modèle `Rapport` utilise le trait `Auditable` (journalisation automatique).

## 6. Conformité

- **RBM/GAR** : chaîne Pilier→…→Activité, IOV, sources de vérification, cibles, taux physique/financier.
- **SYSCOHADA / IPSAS** : ventilation par nature budgétaire (Titres) et chaîne de la dépense complète.
- **COSO** : séparation des fonctions (génération vs signature), piste d'audit, contrôle de cohérence.
- **ISO 9001** : référence + version + traçabilité de chaque document produit.
- **ISO 27001** : intégrité (hash), authenticité (signature HMAC), vérification indépendante (QR public).

## 7. Tests

```bash
php artisan test tests/Feature/ReportingModuleTest.php tests/Feature/ReportingP0Test.php tests/Feature/ReportingPeriodiqueTest.php
```

`ReportingModuleTest` : hub, génération PDF/Excel/Word, versioning, signature, QR, réconciliation.

`ReportingP0Test` (phase P0) : période trimestrielle, alias `reporting.*`, export CSV, verrou signature, cohérence SF/SI/SE.

## 8. Dépendances ajoutées

- `phpoffice/phpword` ^1.4 — génération Word (.docx)
- `endroid/qr-code` ^6.0 — QR codes (backend GD)

## 9. Phase 2

### Lot 1 — Marchés publics / PPM + Contrats ✅ (livré)

Nouveaux domaines de données (migrations + modèles + CRUD + seed démo non destructif) :
- **Marchés** (`marches`) : modèle `Marche`, CRUD `/marches`, permissions `marche.*`, PPM (mode de
  passation, type, montant estimé/attribué, statut), liens vers besoin/engagement/structure.
- **Contrats** (`contrats`) : modèle `Contrat`, CRUD `/contrats`, permissions `contrat.*`, liens
  marché/engagement.
- Seeder `MarchesContratsDemoSeeder` : rattache un marché + contrat à chaque engagement de type
  `marche` + marchés planifiés (idempotent par numéro). Intégré à `SafeDatabaseSeeder`.
- Menu « Marchés & Contrats ». Tests : `tests/Feature/MarchesContratsTest.php`.

Deux rapports ajoutés au module Reporting (mêmes garanties : multi-format, hash, QR, signature) :
- `marches` — **Exécution du PPM** (synthèse + plan de passation).
- `contrats` — **Registre des contrats** : chaîne Besoin → Engagement → Marché → Contrat → Paiement,
  avec montant contractuel, engagé (réel) et payé (paiements validés via la chaîne).

### Lot 2 — Suivi des recommandations ✅ (livré)

- **Recommandations** (`recommandations`) : modèle `Recommandation`, CRUD `/recommandations`,
  permissions `recommandation.*`. Sources : Conférence des Chefs d'État, Conseil des Ministres,
  réunion statutaire, mission d'audit. Suivi : priorité, échéance, statut, taux d'exécution.
- Seeder `RecommandationsDemoSeeder` (8 recommandations, idempotent) dans `SafeDatabaseSeeder`.
- Menu « Recommandations » (section Suivi & Restitution). Tests : `tests/Feature/RecommandationsTest.php`.
- Rapport `recommandations` (rapport 7) : synthèse globale + par source + détail, multi-format.

### Lot 3 — Ateliers & formations ✅ (livré)

- **Formations** (`formations`) : modèle `Formation`, CRUD `/formations`, permissions `formation.*`.
  Type (formation/atelier/séminaire/conférence), participation + répartition genre (H/F), coût,
  source de financement, niveau de satisfaction, impact.
- Seeder `FormationsDemoSeeder` (6 sessions, idempotent) dans `SafeDatabaseSeeder`.
- Menu « Formations & ateliers ». Tests : `tests/Feature/FormationsTest.php`.
- Rapport `formations` (rapport 8) : synthèse + par type + détail (participation, genre, coûts, satisfaction).

### Lots à venir

- **Lot 4** : Inventaires / Immobilisations (informatique, véhicules, bâtiments, mobilier) — rapport 9.
- **Lot 5** : Constructeur de rapports personnalisés.

## 10. Phase P0 — Fondations gouvernance (post-audit AUD-RPT-2026-01)

| Livrable | Détail |
|----------|--------|
| Période paramétrable | Migration `2026_07_03_*`, `RapportPeriodeService`, filtre UI |
| Permissions `reporting.*` | `ReportingPermissionsSeeder` + alias `ReportingPermissionAliases` |
| Policy | `RapportPolicy` + contrôleur migré vers `$this->authorize()` |
| Export CSV | Bouton hub + `RapportRenderer` |
| Verrou signature | `verrouille=true` à la signature ; blocage UPDATE |
| Tests cohérence | SF ↔ `situation_budgetaire`, SI ↔ `indicateurs_evolution`, SE ↔ `suivi_evaluation` |
| Déploiement | `php artisan migrate` + `php artisan permissions:sync-production --force` |

**Audit :** [audit-reporting-institutionnel-2026.md](audit-reporting-institutionnel-2026.md)

## 11. Phase P1 — Rapports institutionnels périodiques

| Type | Clé | Contenu multi-volets |
|------|-----|----------------------|
| Trimestriel | `trimestriel` | Stratégique, budgétaire, physique (campagnes), financier, IOV, réalisations, difficultés, recommandations |
| Semestriel | `semestriel` | Volets communs + comparatif S1/S2 + N/N-1 |
| Annuel | `annuel` | Synthèse PAP + budget + IOV + S&E + historique évaluations |

**Service :** `RapportPeriodiqueService` · intégré à `RapportComposer`.

**Commandes CLI :**

```bash
php artisan reporting:generer-trimestre --exercice=2026 --periode=2026-T1 --format=pdf
php artisan reporting:generer-semestre --exercice=2026 --periode=2026-S1 --format=excel
php artisan reporting:generer-annuel --exercice=2026 --format=pdf
```

**Tests :** `tests/Feature/ReportingPeriodiqueTest.php`

**Prochaine phase (P2)** : rapports performance pilier/axe/département, GED auto, workflow validation MOA.

## 12. Phase P2 — Performance stratégique, catalogue, GED & workflow MOA

| Livrable | Détail |
|----------|--------|
| Rapports stratégiques | `performance_pilier`, `performance_axe`, `performance_departement` via `RapportStrategiqueService` |
| Catalogue | Tables `rapport_categories`, `rapport_modeles` + `ReportingCatalogueSeeder` |
| Workflow MOA | `statut_workflow` : généré → validé → publié ; routes `valider` / `publier` |
| GED auto | `RapportWorkflowService::indexerGedSiBesoin()` post-génération (`config/reporting.php`) |
| Écarts phys./fin. | Sections campagnes/collectes S&E dans `ecarts_physique_financier` |
| Fédération Recettes | `recettes_mobilisation`, `recettes_financier` dans le hub `/reporting` |

**Migration :** `2026_07_04_000001_reporting_p2_catalogue_workflow.php`

**Tests :** `tests/Feature/ReportingP2Test.php`

**Prochaine phase (P3)** : notes IPSAS, planification scheduler, audit reporting.

## 13. Phase P3 — IPSAS, automatisation, audit & API

| Livrable | Détail |
|----------|--------|
| État IPSAS | Type `ipsas_etat_financier` + table `rapports_ipsas` (snapshot JSON) |
| CLI IPSAS | `php artisan reporting:generer-ipsas --exercice=2026 --format=pdf` |
| Scheduler | Trimestriel / semestriel / annuel dans `bootstrap/app.php` (config `reporting.schedule.*`) |
| Planification UI | `/reporting/planification` — statut scheduler + journal audit |
| Audit | Table `audit_reporting` + `RapportAuditService` (download, validate, publish, API…) |
| API REST | `/api/v1/reporting/catalogue`, `/types`, `/rapports`, `/rapports/{id}` |

**Migration :** `2026_07_05_000001_reporting_p3_ipsas_audit.php`

**Variables d'environnement :**

```env
REPORTING_SCHEDULE_ENABLED=true
REPORTING_SCHEDULE_TRIMESTRIEL=true
REPORTING_SCHEDULE_SEMESTRIEL=true
REPORTING_SCHEDULE_ANNUEL=true
```

**Tests :** `tests/Feature/ReportingP3Test.php`

**Recette MOA :** `docs/pv-recette-reporting.md` (PV-REC-RPT-2026-01)
