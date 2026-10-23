# Audit — Module « Suivi-Évaluation »

Audit du 2026-06-14. **Constat : module très complet (~96 %)**, le plus riche du dispositif S&E.
Une seule addition de valeur livrée (bande S&E dans le cockpit exécutif) ; pas de refonte.

## 1. État de conformité

| Domaine | Statut |
|---|---|
| Campagnes de suivi + collectes (workflow saisie→vérifiée→validée) | ✅ |
| Séparation des fonctions (point_focal saisit / commissaire valide) | ✅ |
| Report des taux validés sur l'activité (`appliquerSurActivite`) | ✅ |
| Évaluations périodiques (P0) — score pondéré 5 composantes (physique 30 / IOV 25 / recommandations 20 / formations 10 / conformité 15) | ✅ |
| Niveaux satisfaisant/partiel/insuffisant | ✅ |
| Évaluation d'impact (P1) | ✅ |
| Missions de suivi (P2) | ✅ |
| Plans d'amélioration + actions correctives (P2) | ✅ |
| Recommandations (sources, catégories, taux d'exécution) | ✅ |
| Indicateurs + mesures + feux tricolores + historique mesures | ✅ |
| Consolidation produits/sous-produits + alertes performance (S3/S4) | ✅ |
| Audit suivi physique (score conformité + 6 sections d'anomalies) | ✅ |
| Justificatifs obligatoires (GED) à la validation | ✅ |
| Exports gouvernés (PDF/Excel/Word/CSV, hash/QR/version) | ✅ |
| Hub S&E dédié (`suivi-evaluation.index`) | ✅ |
| **Synthèse S&E dans le cockpit exécutif** | ⚠️ → ✅ (ajoutée) |
| 60 tests (P0-P3, S1-S4, Recommandations, Formations, Impact) | ✅ |

## 2. Écarts résiduels (mineurs, non comblés volontairement)

- **Triplement des espaces de permissions** : `suivi.*` (RoleSeeder), `suivi_evaluation.*`
  (SuiviEvaluationPermissionsSeeder), `suivi_physique.*` (SuiviPermissionsSeeder). Réconciliés par
  `SuiviEvaluationPermissionAliases`. Une unification serait propre mais **risquée** (60 tests +
  policies CampagneSuivi/Collecte vérifient les deux espaces) → laissé en l'état.
- Recommandations/Formations non reliées aux activités/produits PAP (FK ciblage absente) — enrichissement optionnel.
- Pas de déclenchement planifié (cron) des évaluations (génération manuelle + à la clôture de campagne).

## 3. Addition livrée — Bande S&E dans le cockpit exécutif (non destructif)

Le cockpit exécutif (`reporting.cockpit`) ne présentait que des KPI budgétaires/PAP. Ajout **additif**
d'une bande « Performance & Suivi-Évaluation » reliant pilotage budgétaire et performance :
- `ReportingController@cockpit` : nouvelle clé de vue `suiviEval` calculée via services existants
  (`TableauBordService::executionPap` / `feuxIndicateurs`) + dernière `EvaluationPeriodique` de l'exercice.
- Partial autonome `resources/views/reporting/_synthese_se.blade.php` (inclus en tête du cockpit) :
  **score S&E global + niveau**, **réalisation physique / financière PAP**, **feux indicateurs V/O/R**,
  lien vers le hub S&E. Tout est gardé `?? 0` (rendu robuste sur données vides).
- Test : `CockpitActivitesRetardTest::test_cockpit_affiche_la_bande_suivi_evaluation`.
- Aucun graphique existant ni donnée du cockpit n'est modifié (une seule ligne `@include` ajoutée).
