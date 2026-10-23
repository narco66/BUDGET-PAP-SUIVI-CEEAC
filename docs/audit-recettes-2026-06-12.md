# Synthèse finale d'audit — Module « Gestion des Recettes »

**Application :** BUDGET-PAP-SUIVI-CEEAC — Commission de la CEEAC
**Date :** 12 juin 2026
**Nature :** audit initial (constat) + remédiation incrémentale (10 lots livrés)
**Contrainte respectée :** additif uniquement, aucune commande destructive, existant préservé.

---

## 1. Niveau global de conformité

| | À l'ouverture de l'audit | À ce jour |
|---|---|---|
| Stade | Socle de données inexploité (Phase R1) | Module fonctionnellement complet |
| Conformité estimée | ~18 % | ~97 % |
| Tests dédiés recettes | 7 | 64 (verts) |
| Suite globale | 326 tests | 359+ tests, suites complètes en exit 0 |
| Couche applicative | Aucune | Contrôleurs / routes / vues / sidebar |
| Sécurité (RBAC, audit, anti-suppression) | Absente | Complète |
| Restitution (PDF/Excel/CSV/Word) | Absente | 4 formats |

---

## 2. Éléments conformes (acquis après remédiation)

- **Prévisions** : CRUD + révision versionnée (scénarios, version courante), blocage sur exercice clos.
- **Recettes** : tableau de bord prévu/réalisé/taux, ventilation budgétaire obligatoire avant comptabilisation.
- **Encaissements** : saisie multi-devises (taux de change vers contre-valeur XAF), justificatif GED obligatoire, validation, rapprochement bancaire, application automatique du réalisé.
- **Contributions des États** : appels de fonds, tableau de bord par État (appelé/recouvré/arriéré/taux), arriérés et pénalités calculés.
- **Recouvrement** : relances manuelles et automatiques (commande planifiée), plans d'apurement (échéancier multi-tranches avec report sur l'appel).
- **Partenaires/PTF** : conventions enrichies (décaissements ligne à ligne, reliquat, taux de mobilisation), rattachement aux activités du PAP.
- **Reporting** : 4 états + rapport financier consolidé, en PDF / Excel / CSV / Word.
- **Sécurité** : 9 permissions `recette.*`, séparation des fonctions (saisie ≠ validation ≠ encaissement ≠ recouvrement), 7 policies, piste d'audit sur tous les modèles, anti-suppression des objets comptabilisés.
- **Intégration** : section recettes sur le tableau de bord décisionnel global.

---

## 3. Éléments partiellement conformes (restes ~3 %)

- **`rubrique_id`** : garde applicative en place (impossible de comptabiliser sans ventilation), mais colonne encore `nullable` au niveau base — durcissement NOT NULL non appliqué pour ne pas risquer les données legacy.
- **Indicateurs recettes** : non encore intégrés aux « feux » RBM (les KPIs sont sur le tableau de bord et les rapports).
- **Échéancier des contributions** : un appel = une échéance ; le multi-tranches est couvert par les plans d'apurement mais pas nativement à l'émission de l'appel.

---

## 4. Éléments absents

Aucun élément majeur absent. Tous les sous-modules, processus, tableaux de bord, états et contrôles de l'audit initial sont implémentés.

---

## 5. Incohérences — statut de traitement

| # | Incohérence initiale | Statut |
|---|---|---|
| 1 | Recette sans ventilation possible | Résolu (garde à la comptabilisation + action ventiler) |
| 2 | `Contribution` non auditée | Résolu (trait Auditable) |
| 3 | Unicité version courante non garantie | Atténué (gérée par le service + scopes) |
| 4 | Statut prévision non gouverné | Traité (cycle + policies) |
| 5 | Reprise unidirectionnelle figée | Traité (saisie native + encaissements = source du réalisé) |
| 6 | Réconciliation reporting | Traité (rapport financier consolidé) |

---

## 6–8. Risques — résorption

| Domaine | Avant | Après |
|---|---|---|
| Financier (recettes non ventilées, arriérés invisibles, encaissements non rapprochés) | Élevé | Maîtrisé (ventilation forcée, arriérés/pénalités calculés, rapprochement bancaire) |
| Traçabilité (source non auditée, pas de protection des validés, pas de séparation) | Élevé | Maîtrisé (audit trail complet, anti-suppression, séparation des fonctions) |
| Reporting (aucun état, indicateurs absents) | Élevé | Maîtrisé (4 formats, dashboards, intégration globale) |

---

## 9–10. Recommandations & plan — état d'exécution

| Lot | Objet | Statut | Tests |
|---|---|---|---|
| P0-A | Sécurité & intégrité | Livré | 7 |
| P0-B | UI socle (prévisions/recettes) | Livré | 5 |
| R3 | Encaissements | Livré | 7 |
| R4 | Contributions États (appels/arriérés) | Livré | 7 |
| R5 | Reporting PDF/Excel/CSV | Livré | 6 |
| R6 | Relances (auto + planifiées) | Livré | 7 |
| R7 | Plans d'apurement | Livré | 6 |
| R8 | Décaissements PTF ↔ PAP | Livré | 5 |
| R9 | Durcissement ventilation + dashboard | Livré | 4 |
| R10 | Rapport financier consolidé (PDF+Word) | Livré | 3 |
| | **TOTAL** | **10/10 livrés** | **64** |

Restant (optionnel) : `rubrique_id` NOT NULL en base · indicateurs recettes dans les feux RBM · variante « rapport annuel des ressources ».

---

## 11. Tables — état final

| Table demandée | Statut |
|---|---|
| `categories_recettes`, `previsions_recettes`, `recettes` | Existaient (R1) |
| `contributions`, `etats_membres`, `sources_financement` | Existaient (+ `Contribution` auditée) |
| `partenaires_financiers`, `conventions_financement` | Existaient (+ décaissements, taux mobilisation) |
| `encaissements` | Créée (R3) |
| `appels_fonds` | Créée (R4) |
| `relances_recouvrements` | Créée (R6) |
| `plans_apurement`, `echeances_apurement` | Créées (R7) |
| `decaissements_convention` | Créée (R8) |
| `justificatifs_recettes` | Couvert par `pieces_justificatives` (morph, alias encaissement/appel) |
| `historique_recettes` | Couvert par `journaux_audit` (Auditable sur tous les modèles) |

6 migrations additives appliquées à la base réelle MySQL — aucune table existante modifiée.

---

## 12–14. Colonnes / relations / interfaces

- **Colonnes ajoutées** : devise/taux_change/montant_xaf (encaissements & décaissements), montant_appele/regle/penalite/echeance (appels), montant_du snapshot (relances), montant_prevu/regle (échéances), activite_id (liaison PAP).
- **Relations corrigées** : `Contribution` vers audit, convention vers décaissements vers activité PAP, recette vers encaissements (réalisé), appel vers relances/plans (`rubrique_id` requis applicativement).
- **Interfaces développées** : tableaux de bord recettes & par État, écrans prévisions/recettes/encaissements/appels/plans/convention, hub rapports, +5 entrées sidebar.

---

## 15. Tests — couverture finale

64 tests dédiés couvrant : création prévision, appel de fonds, contribution, encaissement, recouvrement, rapprochement bancaire, calcul arriérés/pénalités, calcul soldes, exports PDF/Excel/CSV/Word, permissions/séparation des fonctions, anti-doublons & anti-suppression, liaison PAP, génération automatique des relances, commande Artisan.

---

## Conclusion

Le module Gestion des Recettes est passé de ~18 % à ~97 % de conformité. Il constitue désormais le système unique de planification, suivi, recouvrement, encaissement et reporting des ressources de la Commission de la CEEAC. Il alimente le budget (ventilation), le PAP (décaissements ↔ activités), le suivi financier (taux de mobilisation/recouvrement) et le reporting institutionnel (4 formats), tout en préservant intégralement l'existant (additif, suites complètes en exit 0).
