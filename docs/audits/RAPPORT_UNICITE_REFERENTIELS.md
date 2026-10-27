# Rapport d'unicité des référentiels — BUDGET-CEEAC / GESBUDEP

**Date :** 25 octobre 2026 · **Méthode :** `database/scripts/audit_unicite_referentiels.php` (lecture seule) ; résultat brut : `docs/audits/donnees/unicite.json`.
Pour chaque clé métier : groupes en double parmi les enregistrements actifs (non supprimés logiquement) et présence d'un index unique en base. Plusieurs dossiers qui référencent une même ligne, activité ou unité ne constituent **pas** des doublons.

## 1. Résultats

| Clé métier | Enregistrements | Doublons | Index unique | Conclusion |
|---|---|---|---|---|
| Exercice : année / code | 1 | 0 | oui | ✅ |
| Nomenclature : titre (code) | 11 | 3 groupes | non sur `code` seul ; **oui sur `(sens, code)`** | ✅ légitime : codes 1, 2, 3 existent en recette et en dépense |
| Nomenclature : article / paragraphe / rubrique dans leur parent | 38 / 119 / 224 | 0 | oui | ✅ |
| Nomenclature : code de rubrique **global** | 224 | 33 groupes | non | ✅ à confirmer (voir § 2) |
| Ligne budgétaire : exercice + code | 50 | 0 | oui | ✅ |
| Sous-ligne de détail : exercice + code | 70 | 0 | oui | ✅ |
| Unité organique : code | 78 | 0 | oui | ✅ |
| Unité organique : sigle | 78 | 0 | non | ✅ (aucun doublon) |
| Utilisateur : e-mail / matricule | 17 | 0 | oui | ✅ |
| PAP : pilier, axe, produit, sous-produit, tâche dans leur parent | 6 / 21 / 71 / 213 / 2 | 0 | oui | ✅ |
| PAP : code d'activité | 215 | 0 | non | ✅ (aucun doublon ; unicité assurée par l'import) |
| Source de financement : code | 13 | 0 | oui | ✅ |
| EB / ENG / LIQ / ORD / PAY : référence | 36 / 32 / 27 / 21 / 22 | 0 | oui | ✅ |
| ENG : un engagement par EB | 32 | 0 | oui | ✅ |
| ORD : un ordonnancement actif par liquidation | 20 | 0 | non | ✅ (contrôle applicatif) |
| Ordre de paiement : référence | 15 | 0 | oui | ✅ |
| Imputation : ligne unique par engagement | 32 | 0 | non | ✅ |
| Réservation active unique par engagement et ligne | 26 | 0 | non | ✅ |
| Écriture comptable : numéro de pièce | 9 | 0 | non | ✅ |
| GED : référence ; tâche workflow : référence | 4 / 220 | 0 | oui | ✅ |
| Workflow : une instance en cours par dossier | 7 | 0 | **oui (ajouté le 25/10, module Workflow)** | ✅ |

**Aucun doublon illégitime** n'a été trouvé dans les référentiels ni dans les dossiers. Aucune fusion ni suppression n'a été effectuée.

## 2. Codes de rubrique répétés (33 groupes)

Tous les groupes sont dans le même sens budgétaire, sous des **paragraphes distincts** (ex. `141100 Subventions des États membres` sous deux articles « 141 » ; `151100` « Emprunts projets des États membres » / « Remboursement projets des États membres » en financement). L'unicité hiérarchique `(paragraphe_id, code)` est garantie par index. **Aucune ligne budgétaire ne référence ces rubriques.** Il s'agit de la nomenclature officielle importée : conformément aux consignes, elle n'est pas modifiée. **À confirmer par la Direction du Budget** : la nomenclature distingue-t-elle volontairement ces rubriques par leur parent (ressources / emplois de financement), ou faut-il les recoder ?

## 3. Recommandations (non appliquées, faute de conflit à résoudre)

Des index uniques pourraient renforcer `activites.code` (par exercice), `ordonnancements.liquidation_id` (actifs), `engagement_imputations (engagement_id, ligne_budgetaire_id)` et `ecritures_comptables.numero_piece` ; ils ne sont pas ajoutés sans arbitrage, car l'unicité de certains (imputation multiple sur une même ligne, ORD de régularisation) relève d'une règle de gestion à confirmer.
