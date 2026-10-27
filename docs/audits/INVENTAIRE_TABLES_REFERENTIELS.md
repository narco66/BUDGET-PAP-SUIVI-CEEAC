# Inventaire des tables et référentiels — BUDGET-CEEAC / GESBUDEP

**Date :** 25 octobre 2026 · **Base :** PostgreSQL 18.4, `gest_budget_pap` sur 127.0.0.1:5433 (instance locale du poste, vérifiée par `inet_server_addr()` / `inet_server_port()`), environnement `local`, un seul fichier `.env`.
**Volume :** 217 tables, dont 96 non vides. Comptages relevés le 25/10/2026 au début de l'audit.

## 1. Sources uniques de vérité

| Référentiel | Table(s) | Lignes | Clé métier (unicité garantie en base) |
|---|---|---|---|
| Exercices | `exercices` | 1 (2026, ouvert, actif, XAF) | `annee`, `code` — index uniques |
| Budget 2026 — en-tête | `budgets` | 1 (brouillon, 40 305 795 803 XAF) | `exercice_id` |
| Budget 2026 — lignes | `lignes_budgetaires` | 56 (50 actives, 6 supprimées logiquement) | `(exercice_id, code)` — index unique |
| Sous-lignes de fonctionnement | `lignes_budgetaires_details` | 70 | `(exercice_id, code)` — index unique |
| Sources de financement | `sources_financement`, `ligne_source_financement` | 13 / 59 | `code` — index unique |
| Nomenclature | `budget_titres` → `budget_articles` → `budget_paragraphes` → `budget_rubriques` | 11 / 38 / 119 / 224 | `(sens, code)`, `(titre_id, code)`, `(article_id, code)`, `(paragraphe_id, code)` — index uniques |
| Référentiel organique | `structures`, `user_structures`, `postes` | 78 / 13 / 6 | `code` — index unique ; hiérarchie `parent_id` |
| Utilisateurs et habilitations | `users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, `profils`, `user_profils`, `user_access_scopes` | 17 / 14 / 392 / 17 / 1 044 / 7 / 12 / **0** | `email`, `matricule` — index uniques |
| Planification et PAP | `paps`, `piliers` → `axes` → `produits` → `sous_produits` → `activites` → `taches` | 1 / 12 (6 actifs) / 42 / 92 / 234 / 236 (215 actives) / 4 | codes uniques par parent — index uniques ; `activites.code` sans index unique (aucun doublon) |
| Prévisions de recettes | `previsions_recettes`, `contributions`, `appels_fonds`, `recettes` | 22 / 22 / 1 / 22 | `reference` |

## 2. Dossiers transactionnels (chaîne de la dépense)

| Étape | Table | Lignes | Référence unique | Lien amont |
|---|---|---|---|---|
| EB | `expressions_besoin` (+ `expression_besoin_lignes`) | 36 (+16) | `numero` | `activite_id`, `ligne_budgetaire_id`, `ligne_detail_id`, `structure_id` |
| ENG | `engagements` (+ `engagement_imputations`, `reservations_credits`, `certificats_engagement`) | 32 (+32, 28, 53) | `numero` ; **un engagement par EB** (index unique `expression_besoin_id`) | `expression_besoin_id` |
| LIQ | `liquidations` (+ `services_faits`, `liquidation_pieces`) | 27 (+25, 47) | `numero` | `engagement_id` (plusieurs liquidations partielles possibles) |
| ORD | `ordonnancements` (+ `ordres_paiement`) | 21 (+15) | `numero` | `liquidation_id` |
| PAY | `paiements` (+ `paiement_details`, `paiement_rejets`) | 22 (+9, 7) | `numero` | `ordonnancement_id` (paiements partiels possibles) |
| Comptabilité | `ecritures_comptables` | 9 | `numero_piece` (sans index unique, aucun doublon) | `paiement_id`, `engagement_id` |
| Historique | `historique_depenses`, `workflow_instances`, `workflow_instance_etapes`, `visas_electroniques`, `audit_workflow` | 82 / 122 / 232 / 213 / 336 | — | — |

## 3. Règles de calcul des agrégats budgétaires (déduites du code)

| Agrégat (colonne de `lignes_budgetaires`) | Mise à jour | Règle |
|---|---|---|
| `montant_engage` | `Engagement::reserverCredits` / `libererCredits`, `EngagementService::degager` | Σ imputations des engagements à crédits réservés |
| `montant_liquide` | `ConcerneDepense::appliquerCompteur` (Liquidation) | montant de la liquidation validée, réparti au prorata des imputations (`DepenseCompteurService::repartir`) |
| `montant_ordonnance` | idem (Ordonnancement) | idem |
| `montant_paye` | idem (Paiement, montant XAF) ; annulé si l'instrument est rejeté | idem |
| Disponible | `LigneBudgetaire::disponible` | crédit alloué (révisé, sinon ouvert) − engagé |
| Taux | `SuiviFinancierCalculator::synthese` | engagement = engagé / alloué ; exécution = payé / alloué ; liquidation, ordonnancement, paiement = rapport à l'étape amont |

« Crédit disponible » (alloué − engagé) et « reste à payer » (ordonnancé − payé) sont distingués ; la trésorerie n'est pas déduite des crédits.

## 4. Points d'attention sur le schéma

- `user_access_scopes` est **vide** : avec la règle actuelle (« sans périmètre défini = pas de restriction »), tous les utilisateurs ont un accès global (voir `RAPPORT_FINAL_CONFORMITE_DONNEES.md`).
- Codes de rubrique répétés sous des paragraphes différents (33 groupes, nomenclature officielle importée) : unicité hiérarchique garantie, non référencés par des lignes (voir `RAPPORT_UNICITE_REFERENTIELS.md`).
- `appels_fonds` (1 enregistrement) et `contributions` ne sont pas reliés (`contribution_id` nul).
