# Audit du référentiel complet de reporting — BUDGET-PAP-SUIVI-CEEAC

**Commission de la CEEAC · Date : 14 juin 2026**
**Périmètre : audit en lecture seule (aucune modification du code). Source : état réel du code à la date d'audit.**

> ⚠️ Ce rapport **supersède** `audit-reporting-institutionnel-2026.md` (49 % de conformité, 16 types, périodiques « non livrés »). Cet ancien audit décrit un état antérieur : le code a depuis intégré les phases P2 (catalogue + workflow) et P3 (IPSAS + audit), portant le module à un niveau de maturité nettement supérieur. Les chiffres ci-dessous reflètent le code vérifié le 14/06/2026.

---

## 1. Synthèse exécutive

| Indicateur | Valeur |
|---|---|
| Architecture de génération | **Source unique → sections normalisées → rendu multi-format** (cohérence des chiffres garantie par conception) |
| Types de rapports gouvernés (catalogue central `RapportComposer::TYPES`) | **25** |
| Formats par type gouverné | **4** (PDF, Excel, CSV, Word) |
| Classes d'export Maatwebsite (hors catalogue central) | **~41** |
| Gabarits PDF dédiés | **~26** |
| Tableaux de bord interactifs | **5** (+ graphiques ApexCharts) |
| **Volume d'états/exports distincts produisibles** | **> 80** — la cible « 80–120 » est **atteinte en volume brut** |
| **Taux de couverture du catalogue normatif A–K (sortie dédiée)** | **≈ 68 %** (92/135 lignes) |
| **Taux de couverture en disponibilité de la donnée** (sortie dédiée + écran/rapport paramétrable) | **≈ 98 %** (133/135) |
| Lignes du catalogue normatif **réellement absentes** (ni donnée, ni écran) | **2** (≈ 1,5 %) |

**Conclusion générale.** Le système n'a **pas un problème de rapports manquants** mais un enjeu de **normalisation et de gouvernance du catalogue** : ~40 exports spécifiques aux modules vivent **en dehors** du catalogue central gouverné (`RapportComposer` → versioning, hash SHA-256, signature HMAC, QR, workflow MOA, indexation GED). L'effort prioritaire consiste à **rattacher ces exports au socle gouverné** et à **segmenter les tableaux de bord par persona institutionnel**, plutôt qu'à développer massivement de nouveaux états.

---

## 2. Cartographie de l'architecture de reporting (Phase 1)

### 2.1 Socle de génération (couche métier)

```
ReportingService (source UNIQUE des montants : chaîne dépense + RBM/GAR, réconciliés au global)
        │
        ▼
RapportComposer  ──►  SECTIONS NORMALISÉES  (kpi | table | liste | texte)   ◄── source unique des 4 rendus
        │                         │
        │                ┌────────┼────────┬─────────┐
        ▼                ▼        ▼        ▼         ▼
RapportRenderer        PDF      Excel    Word       CSV
 (orchestration)      DomPDF   Maatweb  PhpWord   Maatweb
        │
        ├─ Checksum SHA-256 des sections (checksum_sources)
        ├─ Hash SHA-256 du binaire (hash)
        ├─ QR de vérification publique (qr_token)         ─ QrCodeService (endroid/qr-code)
        ├─ Signature HMAC-SHA256                           ─ SignatureService
        ├─ Archivage disque  storage/app/rapports/{AAAA}/  (versioning vN)
        ├─ Enregistrement table `rapports`
        ├─ Workflow MOA  genere → valide → publie          ─ RapportWorkflowService
        ├─ Indexation GED non destructive                  ─ GedRapportBridgeService
        └─ Journal d'audit                                 ─ RapportAuditService + JournalAudit
```

**Services confirmés** (`app/Services/Reporting/`) : `ReportingService`, `RapportComposer`, `RapportRenderer`, `RapportPeriodiqueService`, `RapportPeriodeService`, `RapportStrategiqueService`, `RapportIpsasService`, `RapportWorkflowService`, `RapportAuditService`, `WordRenderer`, `QrCodeService`, `SignatureService`, `ActivitesRetardService`, `Fmt`.

**Génération automatique / CLI** : `reporting:generer-trimestre`, `reporting:generer-semestre`, `reporting:generer-annuel`, `GenererRapportIpsas`, base `GenererRapportPeriodiqueCommand` ; captures de snapshots (`CapturerSituationBudgetaire`, `CapturerPerformancesIndicateurs`, `CapturerEvaluationsPeriodiques`).

**Tables** : `rapports`, `rapport_categories`, `rapport_modeles`, `rapports_ipsas`, `audit_reporting` (migrations `2026_06_13` → `2026_07_05`, phases P0→P3).

### 2.2 Catalogue central gouverné — 25 types (`RapportComposer::TYPES`)

| # | Clé | Intitulé | Domaine |
|---|---|---|---|
| 1 | `pap` | Performance du PAP | PAP |
| 2 | `execution_synthese` | Synthèse d'exécution budgétaire | Budget |
| 3 | `execution_axe` | Exécution par axe | Budget/RBM |
| 4 | `bailleur` | Exécution par bailleur (CEEAC/PTF) | Budget/Recettes |
| 5 | `categorie` | Exécution par catégorie | Budget |
| 6 | `rbm_gar` | Technico-financier RBM/GAR | RBM/GAR |
| 7 | `marches` | Exécution du PPM (marchés) | Dépense |
| 8 | `contrats` | Registre des contrats | Dépense |
| 9 | `recommandations` | Suivi des recommandations | S&E |
| 10 | `formations` | Ateliers et formations | RH |
| 11 | `nomenclature` | Nomenclature budgétaire | Référentiel |
| 12 | `budget_institutionnel` | Cycle budgétaire B0–B4 | Budget |
| 13 | `situation_budgetaire` | Situation budgétaire consolidée | Budget |
| 14 | `indicateurs_evolution` | Évolution / écarts IOV | Indicateurs |
| 15 | `ecarts_physique_financier` | Écarts physique / financier | Suivi physique |
| 16 | `suivi_evaluation` | Suivi-évaluation institutionnel | S&E |
| 17 | `trimestriel` | Rapport trimestriel institutionnel | Institutionnel |
| 18 | `semestriel` | Rapport semestriel institutionnel | Institutionnel |
| 19 | `annuel` | Rapport annuel consolidé | Institutionnel |
| 20 | `performance_pilier` | Performance par pilier | Stratégique |
| 21 | `performance_axe` | Performance par axe | Stratégique |
| 22 | `performance_departement` | Performance par département | Stratégique |
| 23 | `recettes_mobilisation` | Mobilisation des contributions | Recettes |
| 24 | `recettes_financier` | Rapport financier des recettes | Recettes |
| 25 | `ipsas_etat_financier` | État financier IPSAS (préparatoire) | Financier |

### 2.3 Exports & gabarits hors catalogue central (gouvernance partielle)

- **Stratégie** : `StrategieCatalogueExport` (visions/plans/orientations/programmes/projets/portefeuilles/objectifs/indicateurs/matrice) + PDF synthèse/matrice/cohérence/pilotage.
- **RBM** : `MatriceRbmExport`, `CadreLogiqueExport`, `IndicateursCatalogueExport`, `NiveauRbmExport` + 5 PDF.
- **Dépense** : `BesoinsExport`, `JournalEngagementsExport`, `JournalLiquidationsExport`, `JournalOrdonnancementsExport`, `JournalPaiementsExport`, `JournalConsolideExport`, `RestesAPayerExport`, `RestesAEngagerExport`, `EcrituresComptablesExport` (Excel + CSV) + PDF besoin/engagement/certificat/étape/réservation.
- **Recettes** : `MobilisationEtatsExport`, `ArrieresExport`, `EncaissementsExport`, `PrevisionsRecettesExport` + PDF mobilisation/arriérés/rapport financier + Word.
- **Référentiel financier** : `SourcesFinancementExport`, `PartenairesFinancierExport`, `ConventionsFinancementExport` + PDF synthèse/sources/PTF.
- **GED** : `GedDocumentsExport`, `GedArchivesExport`, `GedSignaturesExport`, `GedJournalExport` + PDF fiche/certificats/bordereaux.
- **Suivi / Institution / Interop** : `CollectesCampagneExport`, `AnnuaireInstitutionnelExport`, `ApiInventaireExport` + PDF associés.
- **Workflow / Alertes** : exports CSV audit workflow et alertes.

> **Constat clé.** Ces ~41 exports produisent des états réels et exploitables, mais **ne bénéficient pas** systématiquement du versioning, du hash, de la signature, du QR ni du workflow MOA du socle `RapportComposer`. C'est la principale source de **double écosystème** (états gouvernés vs états « directs »).

### 2.4 Tableaux de bord existants (Phase 3)

| Dashboard | Route | Contenu | Graphiques |
|---|---|---|---|
| Accueil | `dashboard` | KPI institutionnels, exécution budget, PAP, engagements | ApexCharts |
| **Cockpit exécutif** | `reporting.cockpit` | Vue stratégique globale, chaîne d'exécution, axe, CEEAC/PTF, départements, évolution mensuelle | 6 graphiques |
| **Cockpit PAP** | `paps.dashboard` | Performance par pilier, produits, activités, alertes, drill-down | 1+ graphiques |
| **Pilotage stratégique** | `strategie.dashboard.decisionnel` | Scores pilotage, cohérence, alignement, perf. programme/projet | oui |
| **Tableaux de bord décisionnels** | `tableaux.index` | Chaîne d'exécution, indicateurs (feux), structures, nature | 4 graphiques |
| Suivi financier | `suivi-financier.index` | 6 taux, restes, écarts PAP, tâches | KPI |

---

## 3. Cartographie de couverture du catalogue normatif A–K (Phases 2 & 6)

**Légende :** ✅ sortie dédiée (rapport/export/dashboard) · 🟡 donnée disponible via rapport paramétrable ou écran, **sans** sortie/export catalogué dédié · ❌ absent (ni donnée, ni écran).

### A. Planification stratégique — 7 ✅ / 3 🟡 / 0 ❌
| Ligne | Statut | Appui |
|---|---|---|
| Vision institutionnelle | ✅ | catalogue Stratégie + dashboard décisionnel |
| Orientations stratégiques | ✅ | entité + export |
| Plan stratégique | ✅ | entité + export |
| Programmes | ✅ | entité + export |
| Projets | ✅ | entité + export |
| Portefeuille de projets | ✅ | entité + export |
| Alignement stratégique | ✅ | matrice + cohérence (PDF/Excel) |
| Programmation pluriannuelle | 🟡 | écran ; pas d'export dédié |
| Programmation annuelle | 🟡 | écran ; pas d'export dédié |
| Cartographie stratégique | 🟡 | matrice d'alignement ; pas de carto graphique dédiée |

### B. PAP — 6 ✅ / 9 🟡 / 0 ❌
| Ligne | Statut | Appui |
|---|---|---|
| PAP annuel | ✅ | type `pap` + fiche PDF |
| PAP par département | ✅ | type `performance_departement` |
| Activités | ✅ | performance activités + cockpit |
| Produits | ✅ | performance produits |
| Historique | ✅ | historique PAP + snapshots |
| Validation | ✅ | circuit workflow + corbeille + audit |
| PAP consolidé | 🟡 | consolidation par structure (dashboard), pas d'export « consolidé » dédié |
| PAP par direction | 🟡 | par structure ; granularité direction partielle |
| PAP par service | 🟡 | idem |
| PAP par programme | 🟡 | via axe/pilier ; lien programme partiel |
| PAP par projet | 🟡 | via RBM ; lien projet partiel |
| Tâches | 🟡 | onglet tâches ; pas d'export dédié |
| Sous-produits | 🟡 | modèle + réalisations ; pas de rapport dédié |
| Révisions | 🟡 | `PapHistorique`/snapshot ; pas d'export dédié |
| Consolidation | 🟡 | consolidation structure ; pas d'export catalogué |

### C. RBM/GAR — 10 ✅ / 4 🟡 / 1 ❌
| Ligne | Statut | Appui |
|---|---|---|
| Piliers | ✅ | niveau + `performance_pilier` |
| Axes | ✅ | niveau + `performance_axe` |
| Produits | ✅ | niveau |
| Activités | ✅ | niveau |
| Tâches | ✅ | niveau (inclut tâches) |
| Indicateurs | ✅ | catalogue indicateurs (PDF/Excel) |
| Cadres logiques | ✅ | cadre-logique (PDF/Excel) |
| Matrices RBM | ✅ | matrice (PDF/Excel) |
| Répertoire des indicateurs | ✅ | catalogue indicateurs |
| Analyse des résultats | ✅ | `rbm_gar` technico-financier |
| Sous-produits | 🟡 | inclus matrice ; pas de sortie dédiée |
| Sources de vérification | 🟡 | inclus matrice ; pas d'export dédié |
| Chaînes de résultats | 🟡 | écran `chaine-resultats` ; export PDF/Excel à confirmer |
| Cartographie des résultats | 🟡 | arbre RBM ; pas de carto dédiée |
| **Théorie du changement** | ❌ | non implémentée |

### D. Budgétaires — 16 ✅ / 4 🟡 / 0 ❌
| Ligne | Statut |
|---|---|
| Budget préparé / arbitré / consolidé / révisé / exécuté | ✅ (cycle B0–B4 + entités dédiées) |
| Exécution budgétaire / Situation budgétaire | ✅ |
| Crédits ouverts / engagés / liquidés / ordonnancés / payés | ✅ (journaux dépense) |
| Restes à engager / Restes à payer | ✅ (exports dédiés) |
| Situation des lignes budgétaires | ✅ |
| Situation par département | ✅ (consommation par structure) |
| Situation par source de financement | ✅ (`bailleur` + sources) |
| Budget adopté | 🟡 (couvert par cycle ; pas d'état « adopté » distinct) |
| Disponibilités budgétaires | 🟡 (calculé ; export dédié partiel) |
| Budget pluriannuel | 🟡 (service multi-annuel ; sortie dédiée partielle) |

### E. Recettes — 8 ✅ / 2 🟡 / 0 ❌
| Ligne | Statut |
|---|---|
| Prévisions / Contributions États / Contributions partenaires / Recouvrements / Encaissements / Arriérés / Mobilisation des ressources / Situation des financements | ✅ |
| Créances | 🟡 (≈ arriérés ; pas d'état « créances » distinct) |
| Situation de trésorerie | 🟡 (`RapprochementBancaire` ; rapport trésorerie dédié partiel) |

### F. Chaîne de la dépense — 11 ✅ / 4 🟡 / 0 ❌
| Ligne | Statut |
|---|---|
| Expression des besoins / Consolidation des besoins / Engagements / Certificats d'engagement / Réservations de crédits / Liquidations / Contrôle du service fait / Ordonnancements / Paiements / Journal des dépenses / Dépenses par source / Dépenses par département | ✅ |
| Dépenses par activité | 🟡 (via écarts ; pas d'export dédié) |
| Dépenses par programme | 🟡 (via axe ; lien programme partiel) |
| Situation des fournisseurs | 🟡 (fournisseurs dans engagements ; état fournisseurs dédié partiel) |

### G. Suivi physique — 5 ✅ / 5 🟡 / 0 ❌
| Ligne | Statut |
|---|---|
| Réalisations physiques / Activités réalisées / Activités en retard / Écarts / Avancement / Performance physique | ✅ |
| Produits atteints / non atteints | 🟡 (performance produits sans vues filtrées dédiées) |
| Tâches réalisées | 🟡 |
| Justificatifs | 🟡 (pièces justificatives/GED ; état dédié partiel) |

### H. Suivi financier — 9 ✅ / 1 🟡 / 0 ❌
| Ligne | Statut |
|---|---|
| Consommation budgétaire / Exécution financière / Taux engagement / Taux liquidation / Taux ordonnancement / Taux paiement / Restes à payer / Restes à engager / Performance financière | ✅ |
| Analyse financière | 🟡 (anomalies + hub ; rapport d'analyse narratif dédié partiel) |

### I. Indicateurs — 6 ✅ / 4 🟡 / 0 ❌
| Ligne | Statut |
|---|---|
| Catalogue / Valeurs cibles / Valeurs réalisées / KPI / Évolution / Écarts / Performance | ✅ |
| Tendances | 🟡 (≈ évolution ; analyse de tendance dédiée partielle) |
| Comparaisons | 🟡 (N/N-1 dans semestriel ; comparateur dédié partiel) |
| Scores | 🟡 (feux + scores S&E ; état scores consolidé partiel) |

### J. Suivi-évaluation — 6 ✅ / 3 🟡 / 1 ❌
| Ligne | Statut |
|---|---|
| Missions / Évaluations périodiques / Recommandations / Plans d'amélioration / Actions correctives / Risques | ✅ |
| Rapports de mission | 🟡 (écran ; export dédié partiel) |
| Évaluations finales | 🟡 (couvert par périodiques ; type « finale » non distinct) |
| Leçons apprises | 🟡 (pas d'entité dédiée) |
| **Évaluations d'impact** | ❌ | non implémentée |

### K. Gouvernance — 8 ✅ / 2 🟡 / 0 ❌
| Ligne | Statut |
|---|---|
| Workflow / Validations / Rejets / Visa électronique / Délais de traitement / Audit / Journalisation / Activités utilisateurs | ✅ |
| Conformité | 🟡 (couvert par audits métier ; rapport conformité consolidé partiel) |
| Contrôle interne (COSO) | 🟡 (audit budget/dépense ; rapport contrôle interne dédié partiel) |

### 3.1 Bilan de couverture

| Catégorie | ✅ | 🟡 | ❌ | Total |
|---|---|---|---|---|
| A Planification stratégique | 7 | 3 | 0 | 10 |
| B PAP | 6 | 9 | 0 | 15 |
| C RBM/GAR | 10 | 4 | 1 | 15 |
| D Budgétaires | 16 | 4 | 0 | 20 |
| E Recettes | 8 | 2 | 0 | 10 |
| F Chaîne de la dépense | 11 | 4 | 0 | 15 |
| G Suivi physique | 5 | 5 | 0 | 10 |
| H Suivi financier | 9 | 1 | 0 | 10 |
| I Indicateurs | 6 | 4 | 0 | 10 |
| J Suivi-évaluation | 6 | 3 | 1 | 10 |
| K Gouvernance | 8 | 2 | 0 | 10 |
| **TOTAL** | **92** | **41** | **2** | **135** |

- **Couverture par sortie dédiée** : 92/135 = **68 %**
- **Couverture en disponibilité de la donnée** (✅+🟡) : 133/135 = **98,5 %**
- **Réellement manquant** : 2/135 = **1,5 %** (Théorie du changement ; Évaluations d'impact)

---

## 4. Catalogue des rapports manquants & à développer (Phases 6.2 / 6.6)

### 4.1 Réellement absents (priorité fonctionnelle)
1. **Théorie du changement** (RBM) — narratif + schéma de causalité Pilier→Impact.
2. **Évaluations d'impact** (S&E) — type d'évaluation distinct des évaluations périodiques.

### 4.2 Donnée présente mais sortie dédiée à créer (« 🟡 » à promouvoir)
- **Stratégie** : exports Programmation pluriannuelle & annuelle ; cartographie stratégique graphique.
- **PAP** : exports dédiés PAP consolidé, par direction/service/programme/projet ; états Tâches, Sous-produits, Révisions.
- **RBM** : sortie Sous-produits, Sources de vérification, Chaîne de résultats (PDF/Excel), Cartographie des résultats.
- **Budget** : état Budget adopté, Disponibilités budgétaires, Budget pluriannuel.
- **Recettes** : état Créances, Situation de trésorerie.
- **Dépense** : Dépenses par activité, par programme, Situation des fournisseurs.
- **Suivi physique** : Produits atteints / non atteints, Tâches réalisées, Justificatifs.
- **Indicateurs** : Tendances, Comparaisons N/N-1, Scores consolidés.
- **S&E** : Rapports de mission, Évaluations finales, Leçons apprises.
- **Gouvernance** : Rapport de conformité, Rapport de contrôle interne (COSO).

### 4.3 Dette de gouvernance (Phase 6.6 — le chantier prioritaire)
**Rattacher au socle gouverné** les ~41 exports module qui n'ont pas encore versioning/hash/signature/QR/workflow/GED. Deux options par export : (a) le déclarer comme `type` dans `RapportComposer` ; (b) le router via `RapportRenderer` pour bénéficier de l'archivage et de la traçabilité.

---

## 5. Cartographie & écarts des tableaux de bord (Phases 3 / 6.5 / 6.7)

| Dashboard cible (Phase 3) | Destinataires | Existant | Écart |
|---|---|---|---|
| **Exécutif** | Président, V-P, Commissaires, SG | `reporting.cockpit` | ✅ existe ; manque **profilage par persona** + alertes critiques priorisées |
| **Stratégique** | DPPB, Commissaires, Directeurs | `strategie.dashboard.decisionnel` + `tableaux.index` + perf. pilier/axe | ✅ existe ; manque **vue unifiée pilier→axe→programme→département** sur un seul écran |
| **Opérationnel** | Directions, Services | `paps.dashboard` (activités/tâches/échéances/alertes) | 🟡 largement couvert ; manque **filtrage par service/échéance** et alertes opérationnelles dédiées |
| **Financier** | Contrôle Financier, Agent Comptable, DPPB | `suivi-financier.index` + `tableaux.index` | ✅ existe ; manque **volet trésorerie/recettes consolidé** dans le même écran |

**Dashboards à développer** : aucun nouveau dashboard structurel requis — l'effort porte sur la **segmentation par rôle** (RBAC déjà disponible : rôles/permissions/périmètres) et l'ajout des volets manquants.

---

## 6. Audit technique (Phase 5)

| Dimension | Constat | Recommandation |
|---|---|---|
| Cohérence des chiffres | **Forte par conception** : source unique `ReportingService`, réconciliation au global, checksum des sections | Étendre le principe aux exports module (cf. §4.3) |
| Génération asynchrone | Commandes CLI + scheduler pour périodiques ; rendu synchrone côté contrôleurs | **Mettre la génération lourde (PDF paysage volumineux, annuel) en file d'attente (Job + queue)** et notifier à l'achèvement |
| Performances SQL / agrégations | Agrégations centralisées dans les services ; snapshots `situations_budgetaires`, `performances_indicateurs`, `evaluations_periodiques` (pré-calcul) | **Vérifier les index** sur clés de jointure (`exercice_id`, `activite_id`, `ligne_budgetaire_id`, morphs d'audit) ; matérialiser les vues de cockpit |
| Cache | Non systématisé sur les dashboards | **Cache court (TTL)** sur agrégats de cockpit par exercice, invalidé aux écritures de compteurs |
| Sécurité | Signature HMAC, QR public, audit immuable (`audit_reporting`, `journaux_audit`), RBAC Spatie étendu | Aligner finement les permissions `reporting.*` sur tous les exports module |
| Traçabilité | Versioning + hash + workflow MOA + GED | Automatiser l'indexation GED post-publication pour **tous** les types |

---

## 7. Recommandations (Phase 6.8)

1. **Ne rien dupliquer.** La cible « 80–120 rapports » est atteinte en volume ; éviter de recréer des états qui existent déjà sous forme paramétrable.
2. **Unifier la gouvernance.** Faire du socle `RapportComposer/RapportRenderer` le **point de passage unique** ; migrer progressivement les exports module pour leur conférer versioning/hash/signature/QR/workflow/GED.
3. **Combler les 2 absences réelles** (théorie du changement, évaluations d'impact) et promouvoir les 🟡 prioritaires en sorties cataloguées.
4. **Segmenter les tableaux de bord par persona** en exploitant le RBAC existant (périmètres), plutôt que créer de nouveaux écrans.
5. **Industrialiser la performance** : file d'attente pour les gros rendus, cache d'agrégats, revue d'index.
6. **Normaliser la charte & les annexes** (pagination, signatures, QR, numérotation) sur tous les gabarits, y compris PowerPoint (format `.pptx` **non encore couvert** — voir §8).

---

## 8. Écart de format : PowerPoint (.pptx)

La Phase 4 exige 5 formats (PDF, Excel, CSV, Word, **PowerPoint**). Le code couvre **PDF/Excel/CSV/Word** ; **`.pptx` n'est pas implémenté**. Recommandation : ajouter un `PptxRenderer` (PhpOffice/PhpPresentation) consommant les **mêmes sections normalisées**, pour les rapports de restitution institutionnelle (exécutif, trimestriel, annuel) — effort ciblé, à brancher sur `RapportRenderer`.

---

## 9. Plan de mise en œuvre priorisé (Phases 6.9 / 6.10)

> Règle transverse : **non destructif** — aucune suppression, aucune rupture ; toute évolution passe par la couche source unique pour préserver la cohérence des chiffres.

| Priorité | Lot | Contenu | Effort | Valeur |
|---|---|---|---|---|
| **P0** | Gouvernance unifiée | Brancher les exports module prioritaires (Dépense, Recettes, Stratégie, RBM) sur `RapportRenderer` (versioning/hash/QR/GED) | M | Élevée — supprime le double écosystème |
| **P0** | Permissions | Aligner `reporting.*` sur tous les exports ; audit d'accès complet | S | Élevée — conformité |
| **P1** | Absences réelles | Théorie du changement, Évaluations d'impact | S | Moyenne |
| **P1** | Dashboards par persona | Profilage exécutif/stratégique/opérationnel/financier via RBAC + volets manquants | M | Élevée — adoption dirigeants |
| **P1** | Format PPTX | `PptxRenderer` sur sections normalisées (exécutif/trimestriel/annuel) | M | Moyenne |
| **P2** | Promotion des 🟡 | Sorties dédiées : PAP consolidé/direction/service ; trésorerie ; créances ; fournisseurs ; produits atteints/non atteints ; tendances/comparaisons | L | Moyenne |
| **P2** | Performance | Jobs en file d'attente pour gros rendus + cache d'agrégats + revue d'index | M | Moyenne |
| **P3** | Conformité avancée | Rapports conformité & contrôle interne (COSO) ; consolidation IPSAS/SYSCOHADA enrichie | M | Moyenne |

**Légende effort :** S ≤ 2 j · M ≈ 3–8 j · L > 8 j (indicatif).

---

## 10. Conclusion

Le référentiel de reporting de **BUDGET-PAP-SUIVI-CEEAC** est **mature et architecturalement sain** : source unique, composition normalisée, rendu multi-format cohérent, gouvernance (versioning, signature, QR, workflow MOA, GED) et traçabilité immuable. En **volume**, la cible de 80–120 états est **déjà atteinte**. L'enjeu réel n'est pas la création massive d'états mais :

1. **l'unification de la gouvernance** des ~41 exports module sous le socle central (P0) ;
2. **la segmentation des tableaux de bord par persona institutionnel** (P1) ;
3. **deux absences fonctionnelles** à combler et **un format (PPTX)** à ajouter ;
4. **l'industrialisation des performances** (file d'attente, cache, index).

Couverture : **68 % en sorties dédiées**, **98,5 % en disponibilité de la donnée**, **1,5 % réellement manquant**. Le projet est donc en position de **finition/normalisation**, non de construction.

---

*Document d'audit — version 1.0 — 14 juin 2026. Méthode : inventaire automatisé (routes, contrôleurs, services, exports, vues, modèles, migrations) + vérification ciblée du code source. Aucune modification appliquée au code.*
