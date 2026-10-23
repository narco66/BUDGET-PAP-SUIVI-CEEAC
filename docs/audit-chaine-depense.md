# Audit — Module « Chaîne de la Dépense / Exécution Budgétaire »

Audit du 2026-06-14. **Constat : module quasi complet (~96 %)**, le plus abouti de l'application.
Une seule addition de valeur a été livrée (cockpit) ; aucune correction structurelle nécessaire.

## 1. État de conformité

| Domaine | Statut |
|---|---|
| Chaîne complète Besoin→Engagement→Liquidation→Ordonnancement→Paiement | ✅ |
| Réservation de crédits atomique (lockForUpdate, all-or-nothing) | ✅ |
| Workflow multi-acteurs + séparation des fonctions (SOD) | ✅ |
| Compteurs idempotents (`compteurs_appliques`) + répartition prorata | ✅ |
| Invariant payé≤ordonnancé≤liquidé≤engagé≤alloué (CHECK MySQL) | ✅ |
| Liquidation partielle (DepenseQuotaService, montantRestantLiquidation) | ✅ |
| Rapprochement bancaire (auto + manuel) | ✅ |
| Multi-devise (DevisePaiementService, montant_xaf) | ✅ |
| Écritures comptables SYSCOHADA + export TOMPRO | ✅ |
| GED polymorphe (pièces justificatives) + PDF DomPDF | ✅ |
| Consolidation des besoins + détection doublons | ✅ |
| Audit de conformité (`DepenseAuditService`, score + anomalies) | ✅ |
| Dossier unifié (timeline besoin→paiement) | ✅ |
| Exports (journaux, restes à payer/engager, écritures) | ✅ |
| **Cockpit d'exécution consolidé** | ⚠️ → ✅ (ajouté) |
| 69 tests `DepenseTest` + `DepenseD0..D5` + `DepenseAval` + `WorkflowDepense` + `GouvernanceExport` | ✅ |

## 2. Inventaire (synthèse)

- **Modèles** : ExpressionBesoin, Engagement(+Imputation), Liquidation, Ordonnancement, Paiement,
  ReservationCredit, CertificatEngagement, ServiceFait, OrdrePaiement, PaiementDetail, PaiementRejet,
  EcritureComptable, RapprochementBancaire(+Ligne), BesoinConsolidation, HistoriqueDepense, AuditDepense.
- **Registre** `app/Depense/Etapes.php` pilotant le contrôleur générique `EtapeDepenseController`.
- **Trait** `ConcerneDepense` (appliquerCompteur idempotent). **Service** `DepenseCompteurService` (répartition).
- **15 services** `app/Services/Depense/*` (audit, consolidation, devise, écritures, dossier, quota, reporting…).
- **Permissions** : besoin/engagement/liquidation/ordonnancement/paiement.* + ged.* ; circuits workflow
  BESOIN(3)/ENGAGEMENT(CF)/LIQUIDATION(CF)/ORDONNANCEMENT(SG)/PAIEMENT(comptable).

## 3. Écarts résiduels (mineurs, non bloquants)
- Paiement partiel : autorisé par le code, mais pas de validation UI/test dédié du multi-partiel paiement.
- Rapprochement limité aux paiements (cohérent : le rapprochement bancaire porte sur les décaissements).

## 4. Addition livrée — Cockpit d'exécution de la dépense (non destructif)

- `CockpitDepenseController@index` → `GET /depense-cockpit` (`depense.cockpit`), route littérale **hors**
  du wildcard `depense/{type}`. Gaté `engagement.voir`. Entrée de menu « Cockpit de la dépense ».
- Vue `resources/views/depense/cockpit.blade.php` : **lecture seule**, réutilise les services existants
  sans dupliquer de calcul :
  - KPIs alloué / engagé / liquidé / payé + taux (`TableauBordService::executionBudgetaire`).
  - Progression de la chaîne (barres % du crédit alloué) + restes à engager / à payer / disponible.
  - Engagements par statut (`engagementsParStatut`).
  - Exécution par tâche, top 12 (`DepenseReportingService::executionParTache`).
  - Carte conformité : score + anomalies par gravité (`DepenseAuditService::executer`, sans persistance)
    + lien export journal consolidé.
- Tests `tests/Feature/CockpitDepenseTest.php` : accessibilité + rendu de la chaîne, refus sans droit.
