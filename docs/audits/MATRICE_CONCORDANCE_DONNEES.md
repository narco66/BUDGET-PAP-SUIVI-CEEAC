# Matrice de concordance des données — affichage ↔ PostgreSQL

**Date :** 25 octobre 2026 · **Exercice :** 2026 · **Base :** `gest_budget_pap` (127.0.0.1:5433)
**Méthode.**
- Valeurs de référence lues en base (`scratchpad/pw/attendus.php`).
- Pages lues dans Chrome via Playwright (`concordance.mjs`), ligne de tableau par ligne, cellule par cellule, pagination suivie.
- Exports Excel relus avec PhpSpreadsheet, dans une transaction annulée (`verif_exports.php`).
- Recalcul indépendant des compteurs (`database/scripts/audit_calculs_budgetaires.php`).

Une ligne est **concordante** quand le montant enregistré en base figure parmi les montants affichés sur la ligne du même dossier ou du même code.

## 1. Listes et exports

| Module | Page / export | Contrôle | Résultat | Écart | Preuve |
|---|---|---|---|---|---|
| EB | `/besoins` | montant estimé par référence | **36/36** | aucun | 8 profils |
| ENG | `/engagements` | montant total | **32/32** | aucun | 8 profils |
| LIQ | `/depense/liquidation` | montant | **27/27** | aucun | 8 profils (un passage en 500 : défaut d'environnement, § 3) |
| ORD | `/depense/ordonnancement` | montant | **21/21** | aucun | 8 profils |
| PAY | `/depense/paiement` | montant | **22/22** | aucun | 8 profils |
| Budget | `/budget` | ouvert, engagé et disponible par code de ligne | **50/50** | aucun | 8 profils |
| Exports | `/depense/export/{besoins,engagements,liquidations,ordonnancements,paiements}` (xlsx) | présence et montant de chaque dossier | **138/138** dossiers, tous montants justes | aucun | transaction annulée, GED inchangée |

## 2. Tableaux de bord et agrégats

| Module | Page | Champ | Valeur affichée | Valeur PostgreSQL | Écart | Cause | Correction |
|---|---|---|---|---|---|---|---|
| Budget | `/dashboard`, `/budget`, `/depense-cockpit` | ouvert, engagé, liquidé, ordonnancé, payé, disponible | 40 305 795 803 / 68 311 725 / 51 466 725 / 41 431 725 / 28 581 725 / 40 237 484 078 | identiques | aucun | — | — |
| Budget | Compteurs des 50 lignes | engagé, liquidé, ordonnancé, payé | Σ stockés | Σ recalculés depuis les dossiers | **0,00** | — | — |
| ENG | ENG-2026-0013 (table de suivi) | réservation active | 2 000 000 | imputation 1 200 000 | 800 000 | dégagement partiel ne réduisant pas la réservation | code corrigé (`EngagementService::degager`) et donnée réconciliée, journalisée (`depense:reconcilier-reservations`) |
| PAY | Rapports, notifications, interopérabilité | montant payé | paiements « validés » : 29 361 725 | réglés (instrument non rejeté) : 28 581 725 | 780 000 | PAY-2026-0020 rejeté par la banque compté comme payé | portée `Paiement::regles()` appliquée (audit, reporting, Tompro, notification de paiement partiel) |
| Dépense | Exécution par tâche (`DepenseReportingService`) | liquidé, ordonnancé | multiplié par le nombre de paiements partiels | une fois par dossier | potentiel (aucune tâche rattachée aujourd'hui) | jointure paiements → ORD → LIQ | une agrégation par étape |
| Reporting | `/reporting/cockpit` | exécution par axe | une barre « — » de 40,3 Md | 21 axes + Hors PAP (Σ = 40 305 795 803) | répartition absente | enveloppes « ACT-{axe} » non rattachées | `parAxe` rattache les enveloppes à leur axe |
| Reporting | `/reporting/cockpit` | décaissements mensuels | 0 sur 12 mois | 28 581 725 en octobre | 28 581 725 | aucune `date_paiement` saisie | date de validation en repli |
| Reporting | `/reporting/cockpit` | alerte « activités en retard » | 215 (taux physique < 30 %) | 0 (échéance des tâches dépassée, même page) | règle divergente | deux règles sur la même page | alerte alignée sur la section de détail |
| Structure budget | `/structure-budget/tableau-bord` | liaisons activité ↔ ligne | « 12 désynchronisées » | 12 activités sur enveloppes partagées (cas normal) | faux positif | règle 1:1 appliquée aux enveloppes | règle et réconciliation corrigées |
| Recettes | `/tableaux-bord` | contributions recouvrées | 2 333 333 / 5 000 000 (46,7 %) | `appels_fonds` : AF-SPA-NAV-1 5 000 000 / 2 333 333,33 | aucun (calcul juste) | source = un appel de fonds d'essai, non relié à `contributions` | **à arbitrer** (§ 4) |
| Stratégie | `/strategie/dashboard/decisionnel` | cohérence | 75 % | référentiel vide (0 objectif, indicateur, risque, matrice) | indicateur trompeur | contrôles sans base comptés à 100 % | « non évaluable » |
| Tableaux de bord | `/tableaux-bord` | graphiques feux / nature | « NaN » | 0 mesure | affichage | anneau 0/0/0 | message explicite |
| Interface | En-tête de toutes les pages | exercice | « Exercice 2026 · XAF » codé en dur | exercice actif (2026, XAF) | aucun aujourd'hui | texte fixe | lu en base (`Exercice::courant()`) |
| Interface | Formulaire EB | libellés des groupes d'imputation | « Budget 2026 » codé en dur | exercice du dossier | aucun aujourd'hui | texte fixe | lu en base |

## 3. Anomalies d'environnement constatées pendant le parcours

| Page | Constat | Cause | Correction |
|---|---|---|---|
| `/depense/liquidation`, `/contrats`, `/dashboard` (un passage chacun) | HTTP 500 « No application encryption key » ; une tentative de connexion PostgreSQL sur le port **5432 avec l'utilisateur root** | Apache + PHP thread-safe : `.env` lu vide sous requêtes concurrentes (valeurs par défaut de Laravel) | `Env::disablePutenv()` (`bootstrap/app.php`) : 0 erreur sur 180 requêtes simultanées (contre 2/60 avant) |

## 4. Données officielles à arbitrer (non modifiées)

1. **Équilibre budgétaire 2026** : crédits de dépense 40 305 795 803 XAF ; prévisions de recettes 40 305 595 800 XAF ; écart **200 003 XAF**. À valider par la Direction du Budget.
2. **AF-SPA-NAV-1** (appel de fonds de 5 000 000, créé le 23/10/2026 par le gestionnaire budgétaire lors d'essais de navigation du SPA) : il alimente les indicateurs de recouvrement. Il n'est rattaché à aucune contribution, tandis que les contributions affichent 0 réalisé. Il faut décider s'il s'agit d'une donnée d'essai à annuler selon la procédure, ou le rattacher à sa contribution.
3. **Paiements sans date d'exécution** : les 8 paiements réglés n'ont pas de `date_paiement`. La date de validation est utilisée par défaut dans les états mensuels.
