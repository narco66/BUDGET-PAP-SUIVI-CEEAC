# Audit du module Plan Annuel de Performance (PAP)

Date : juin 2026 — TB-PAPA-CEEAC / GEST-BUDGET-PAP CEEAC

## Synthèse

Le module PAP repose sur **deux couches** :

1. **Enveloppe workflow** (`paps`) — statut, version, circuit de validation institutionnel.
2. **Contenu RBM/GAR** (`piliers → axes → produits → sous-produits → activités → tâches → indicateurs`) lié par `exercice_id`.

Les correctifs appliqués renforcent la cohérence, la traçabilité et les calculs de performance **sans suppression de données**.

---

## Écarts identifiés et traitements

| Domaine | Constat | Correctif |
|---------|---------|-----------|
| Élaboration | RBM modifiable même si PAP publié | Verrouillage via `PapReferentielService` dans `RbmController` |
| Validation | `pap.valider` peu utilisé ; `agir` ne vérifiait que `pap.voir` | Policy `agir` + `WorkflowService::peutAgir` |
| Versioning | `version` jamais incrémentée | Incrément à resoumission après rejet ; table `pap_historiques` |
| Consolidation PAP | Absente au niveau enveloppe | `PapPerformanceService::consolidationParStructure` + UI show |
| Suivi financier TB | Taux déclaratif (collectes) dans dashboards | **Corrigé** : financier réel = `montant_paye` / budget ; sync auto au paiement |
| Collecte | Pas de garde-fou exercice | Vérification activité ↔ exercice campagne |
| Suivi vs PAP | Campagnes ouvertes sans PAP publié | Blocage si PAP existe et n'est pas `publie` |
| Soumission | Sans contrôle de complétude | `PapAuditService` bloque anomalies critiques |
| Audit | Pas de commande dédiée | `php artisan pap:audit` |

---

## Chaîne RBM/GAR vérifiée

```
Pilier → Axe → Produit → Sous-produit → Activité → Tâche → Indicateur → Cible → Budget
```

Contrôles automatiques (`PapAuditService`) :

- Pilier sans axe (warning)
- Produit sans activité (warning)
- Aucune activité (critique — bloque soumission)
- Activité sans indicateur (warning)
- Indicateur sans cible (warning)
- Activité avec montant sans ligne budgétaire (warning)
- Liaison activité ↔ budget désynchronisée (warning → `budget:reconcilier`)

---

## Workflow de validation

Circuit actuel (5 étapes) : chef de service → directeur → commissaire → SG → président.

Traçabilité : étapes workflow + journal audit + `pap_historiques` (soumission, publication).

---

## Commandes

```bash
php artisan migrate
php artisan pap:audit
php artisan pap:audit --exercice=2026 --json
php artisan budget:reconcilier --dry-run
php artisan budget:rapprocher-pap --exercice=2026 --dry-run
php artisan pap:sync-taux-financiers --exercice=2026
php artisan test --filter=PapModuleTest
```

---

## Fichiers clés

- `app/Services/Pap/PapPerformanceService.php` — calculs performance
- `app/Services/Pap/PapAuditService.php` — diagnostic intégrité
- `app/Services/Pap/PapReferentielService.php` — verrouillage + historisation
- `app/Console/Commands/PapAuditCommand.php` — commande audit
- `app/Models/PapHistorique.php` — snapshots
- `tests/Feature/PapModuleTest.php` — tests régression

---

## Phase 2 — compléments (juin 2026)

| Fonctionnalité | Implémentation |
|----------------|----------------|
| Liaison PAP ↔ piliers | `piliers.pap_id` (FK nullable, rétro-remplie) |
| Révision post-publication | Statut `en_revision`, route `paps.revision`, historique `ouverture_revision` |
| Import PAP + budget | Liaison auto par `ligne_code` ou code activité + `RbmBudgetSyncService` |
| Rapprochement post-import | `PapBudgetRapprochementService` + `budget:rapprocher-pap` + UI import budget |
| Verrou import | `BudgetImportController` refuse si PAP verrouillé |
| Classement départements | `PapPerformanceService::classementDepartements()` |
| Performance activités | `PapPerformanceService::performanceActivites()` |

## Pistes restantes (non bloquantes)

- Aligner le libellé du circuit avec Service → Direction → Département → DPPB (évolution procédure CEEAC).
- ~~Clôturer automatiquement le PAP (`clos`) lors de la clôture de l'exercice.~~ ✅ P4

---

## Plan de correction — phases livrées (juin 2026)

| Phase | Objectif | Livrables clés |
|-------|----------|----------------|
| **P0** | Complétion données | `PapCompletenessService`, `pap:completer-donnees`, `PapP0Seeder` |
| **P1** | UI pilotage | `PapPilotageService`, `/paps/pilotage`, onglets performance fiche PAP |
| **P2** | Enveloppe & workflow | CRUD PAP, workflow DPPB 6 étapes, notifications, champs enveloppe |
| **P3** | GAR complet | Risques, mesures correctives, écarts, snapshots complets à publication |
| **P4** | Intégrations | Chaîne dépense, alignement programmation↔activités, clôture auto PAP |
| **P5** | Qualité | Alias permissions EN→FR, index BDD, export liste Excel, tests `PapP5Test` |

### P5 — détail

- **`PapPermissionAliases`** : `pap.view` → `pap.voir`, `pap.create` → `pap.creer`, etc. (via `Gate::before`)
- **Index** : `pap_historiques(pap_id, version|action)`, `pap_risques(pap_id, statut)`
- **Export** : `GET /paps/export/liste` — Excel des enveloppes PAP
- **Tests** : alias permissions, export, rapport PAP PDF/Excel/Word/CSV, suppression avec piliers
