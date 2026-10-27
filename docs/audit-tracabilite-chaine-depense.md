# Traçabilité intégrale de la chaîne de dépense EB → ENG → LIQ → ORD → PAY

**Date :** 26/10/2026 · **Périmètre :** BUDGET-CEEAC / GESBUDEP, chaîne de la dépense · **Base :** PostgreSQL 18 (gest_budget_pap)

## 1. Synthèse

Depuis n'importe quelle référence de la chaîne (EB, ENG, LIQ, ORD ou PAY), un nouvel écran répond désormais aux questions de suivi :

- d'où vient le dossier ;
- où il se trouve ;
- qui l'a traité en dernier, et si ce traitement était humain ou automatique ;
- qui doit agir, pour quelle action, depuis quand et avant quelle échéance ;
- qui interviendra ensuite ;
- ce qui reste à faire, à quel montant, avec quelles pièces, et quels blocages subsistent.

Le dispositif est **en lecture seule**. Il n'écrit rien, n'attribue aucun dossier et ne corrige aucune décision. Il ne crée aucun acteur et n'invente aucun nom. Une information absente est **signalée**, par exemple : « Affectation manquante — Intervention administrative requise ».

Aucune migration n'a été nécessaire : toutes les données existaient déjà (instances et étapes de workflow, journal `audit_workflow`, tâches « Mes Tâches », fonctions, postes et structures des utilisateurs, imputations, pièces).

| Indicateur (base réelle, 26/10/2026) | Valeur |
|---|---|
| Dossiers de la chaîne tracés | 156 |
| Actions attendues identifiées | 35 |
| Affectations manquantes (blocages réels) | 2 (PAY-2026-0016, PAY-2026-0018) |
| Retards détectés | 2 |
| Écritures en base pendant la traçabilité | 0 (empreinte vérifiée) |

## 2. Audit de l'existant (phase 1, sans modification)

| Exigence | Avant | Constat |
|---|---|---|
| Recherche universelle par référence | Absente | Dossier unifié accessible depuis l'EB et l'ENG seulement |
| Origine du dossier (remontée LIQ/ORD/PAY → EB) | Partielle | Les relations existent ; aucune vue ne remontait depuis LIQ, ORD ou PAY |
| Plusieurs liquidations, ordonnancements et paiements | Partielle | Un seul engagement par EB, **par conception** : index unique `engagements_expression_besoin_id_unique`. La multiplicité porte sur les LIQ (partielles), les ORD (fractionnés) et les PAY (partiels). |
| Dernier acteur (fonction, rôle, structure) | Nom seul | Pas de distinction humain / automatique |
| Décision après un renvoi | Perdue à l'écran | Le moteur remet l'étape « à traiter » et efface sa décision ; seul le journal `audit_workflow` la conserve |
| Acteur attendu | Partiel | Rôle affiché, sans titulaires effectifs ni motif d'exclusion |
| Prochain acteur et branches | Absent | La branche d'ordonnancement (seuil SG / Président) n'était pas anticipée |
| Délais par étape | Absent | Dates disponibles, non exploitées |
| Détection des blocages | Absente | — |
| Historique consolidé de la chaîne | Absent | Historique cloisonné par circuit |

> Correction d'un constat antérieur : la limite « un seul engagement affiché par EB » n'est pas un défaut. Elle reflète une contrainte d'unicité en base.

## 3. Ce qui a été livré

### 3.1 Service `App\Services\Depense\TracabiliteDepenseService` (lecture seule)

- **`dossier($objet)`** : remonte à l'EB d'origine depuis n'importe quel maillon, puis reconstruit l'arbre complet (toutes les LIQ, tous les ORD et tous les PAY). Un dossier non rattaché est tracé et signalé (`origine_absente`).
- **`situation($objet)`**, pour chaque nœud :
  - **position** : circuit, étape, date d'entrée et échéance ; ou tâche de préparation (soumettre, corriger, préparer, solder) ;
  - **dernier acteur** : nom, fonction ou poste, rôle, structure, action, date, commentaire et visa. Il est calculé à partir des décisions d'étape **et** du journal `audit_workflow`, ce qui permet de retrouver les renvois, dont la décision est effacée sur l'étape ;
  - **démarrage ou génération automatique** : détecté quand il suit la validation du dossier parent à moins de 120 s d'intervalle. Le dossier est alors attribué à « Transition automatique — Système BUDGET-CEEAC », et le **dernier acteur humain** est conservé à part ;
  - **acteur attendu** : la prise en charge nominative si elle existe, sinon les titulaires effectifs calculés par la règle d'affectation unique (`AffectationTacheService` et `TacheQueryService::peutTraiter`, qui intègre la séparation des fonctions). Sans titulaire, le service signale l'affectation manquante et indique **pourquoi** chaque porteur du rôle est écarté ;
  - **prochain acteur** : étape suivante du circuit, ou premier acteur du processus suivant. La branche d'ordonnancement est calculée avec `SeuilOrdonnancementService::snapshotPour()`, une méthode qui ne fait que lire le paramètre (sans écrire l'ordonnateur). Un solde restant à payer est signalé ;
  - **étapes restantes** du circuit en cours.
- **Délais** : durée de chaque étape et jours ouvrés, retard par rapport à l'échéance, circuits rejetés ou annulés inclus.
- **Historique** : créations, soumissions, validations, rejets, renvois et reprises, dans l'ordre chronologique, avec fonction, rôle, structure, statuts avant et après, commentaires et traitements pour le compte d'autrui.
- **Finances** : montants engagés, liquidés, ordonnancés et payés, sans double comptage (dossiers rejetés ou annulés exclus, instruments rejetés exclus). Restes à liquider et à payer. Pour chaque ligne budgétaire imputée : part du dossier, crédit, engagé, liquidé, payé et disponible.
- **Documents** : pièces jointes de tous les maillons (avec auteur, date et téléchargement), toutes indexées en GED.
- **Blocages** :

| Code | Gravité | Règle |
|---|---|---|
| `tache_manquante` | bloquante | Étape ou action attendue sans tâche « Mes Tâches » active |
| `affectation_manquante` | bloquante | Aucun utilisateur actif éligible ; motifs d'exclusion détaillés |
| `affectataire_inactif` | bloquante | Tâche prise en charge par un compte désactivé |
| `transition_non_finalisee` | bloquante | Statut « soumis » sans circuit en cours |
| `retard` | majeure | Échéance de la tâche dépassée |
| `notification_manquante` | majeure | Action attendue sans notification d'action en attente |
| `rattachement_manquant` | majeure | LIQ validée sans ORD, ou ORD signé sans PAY |
| `affectation_par_defaut` | avertissement | Aucun titulaire organique paramétré : tâche ouverte à tous les porteurs du rôle |
| `origine_absente` | avertissement | Aucune EB d'origine |

### 3.2 Écran « Chaîne de la dépense → Traçabilité des dossiers »

- **`/depense/tracabilite`** :
  - recherche universelle par référence (EB, ENG, LIQ, ORD, PAY ou OP), par objet, par bénéficiaire, par référence bancaire ou par montant ;
  - un résultat unique ouvre directement la fiche ;
  - indicateurs : étapes en attente, retards, tâches non prises en charge, affectations par défaut, dossiers retournés.
- **`/depense/tracabilite/{type}/{id}`** :
  - en-tête ;
  - vue graphique EB → PAY (fait, en cours, à venir, rejeté) ;
  - bloc **Situation actuelle** (où, dernier traitement, qui doit agir, action attendue, ensuite, autres actions attendues dans la chaîne) ;
  - **7 onglets** : Synthèse · Circuit et délais · Historique · Dossiers liés · Finances · Documents · Blocages.
- **Liens « Traçabilité »** ajoutés sur les fiches EB, ENG, LIQ/ORD/PAY, sur le dossier unifié et sur la fiche de tâche. Entrée ajoutée au menu « Chaîne de la dépense ».
- **Sécurité** :
  - chaque fiche exige `authorize('view')` ;
  - la recherche est filtrée par le périmètre (`AccessScopeService`) puis par la policy de chaque résultat.

## 4. Blocages réels détectés sur la base

| Dossier | Constat | Action proposée (à décider, non exécutée) |
|---|---|---|
| PAY-2026-0016 | Étape « Comptable » : le seul comptable actif est l'initiateur du paiement, que la séparation des fonctions écarte. Échéance dépassée. | Affecter un second comptable, ou désigner un suppléant ou un intérimaire |
| PAY-2026-0018 | Étape « Agent comptable » : le seul agent comptable actif est l'initiateur. Échéance dépassée. | Désigner un agent comptable suppléant ou un intérimaire |

Ces deux dossiers n'ont été ni réaffectés ni modifiés : leur résolution relève d'une décision administrative.

## 5. Tests

**SQLite — `tests/Feature/TracabiliteDepenseTest.php` : 14 tests sur 14 réussis (65 assertions).**

| # | Cas |
|---|---|
| 1 | Recherche par référence avec ouverture directe ; recherche par bénéficiaire |
| 2 | Depuis chaque maillon (EB, ENG, LIQ, ORD), la même chaîne complète et la même origine |
| 3 | EB en validation : étape, dernier acteur, titulaires attendus, étapes restantes, prochain acteur |
| 4 | Retour pour correction : auteur du renvoi, motif, action attendue chez le demandeur |
| 5 | Aucun titulaire : affectation manquante, aucun nom inventé, état « bloqué » |
| 6 | Plusieurs LIQ, ORD et PAY tracés |
| 7 | Branche d'ordonnancement selon le seuil (SG ou Président) |
| 8 | Paiement partiel : montants, reste à payer, prochaine étape « solde » |
| 9 | Historique chronologique avec les décisions |
| 10 | Accès refusé sans droit (403) ; recherche filtrée |
| 11 | Affectataire inactif signalé |
| 12 | Lecture seule : aucune écriture, ordonnateur non écrit |
| 13 | Écran complet : Situation actuelle et 7 onglets |
| 14 | Retard détecté |

**Recette PostgreSQL — `tests/Recette/TracabiliteRecetteTest.php`** (transaction annulée) : **2 tests sur 2 réussis (197 assertions).**

- Les 156 dossiers réels sont tracés sans erreur, et l'empreinte de 10 tables reste identique avant et après.
- Recherche « EB-2026-0040 » ; fiches EB et ORD affichées dans les deux sens.

## 6. Contraintes respectées

- Aucune migration, aucune réinitialisation, aucune donnée modifiée : Budget 2026, référentiel organique, nomenclature, utilisateurs, rôles et permissions sont intacts.
- Aucune réaffectation silencieuse, aucune décision corrigée, aucun acteur ni lien financier créé.
- Les historiques de décision sont lus dans le journal append-only, qui n'est pas modifié.

## 7. Points ouverts

1. **Renvoi et étape réinitialisée** : le moteur efface la décision sur l'étape lors d'un renvoi. La traçabilité compense par le journal. Si l'on souhaite conserver la décision sur l'étape elle-même, il faut modifier le moteur.
2. **Délais ouvrés** : seuls les week-ends sont exclus. Le calendrier des jours fériés CEEAC reste à paramétrer.
3. **Performance** : environ 165 ms par dossier complet. Un cache par requête est à envisager si l'écran est intégré à des listes.
4. **Blocages PAY-2026-0016 et PAY-2026-0018** : une décision d'affectation est attendue (§4).
