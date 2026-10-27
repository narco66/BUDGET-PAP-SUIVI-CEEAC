# Contrôle des données affichées par les vues — BUDGET-CEEAC / GESBUDEP

**Date :** 26 octobre 2026 · **Exercice :** 2026 · **Base :** PostgreSQL 18 `gest_budget_pap` (127.0.0.1:5433), lue sans écriture.

Ce rapport réunit, du point de vue des vues, les contrôles de données de l'audit global des données (25 octobre, détail cellule par cellule dans `MATRICE_CONCORDANCE_DONNEES.md`) et ceux de la campagne de modernisation (26 octobre). Les corrections d'interface de la campagne (palette, menu, accessibilité) ne touchent ni aux requêtes ni aux calculs : les concordances établies restent valables. Ce point a été revérifié par la suite de tests et par la relecture des pages clés après correction.

## 1. Méthode

1. **Valeurs de référence** lues directement en base (`attendus.php`), recalcul indépendant des compteurs budgétaires (`database/scripts/audit_calculs_budgetaires.php`).
2. **Lecture des pages dans Chrome** (Playwright) : chaque ligne de tableau, pagination suivie, pour **8 profils** connectés (gestionnaire budgétaire, chef de service, chef de service MG, contrôleur financier, agent comptable, directeur, auditeur, lecteur).
3. **Exports** relus avec PhpSpreadsheet, dans une transaction annulée.
4. **Valeurs aberrantes** : recherche de « NaN », « undefined », « Infinity » et de montants « 0 » suspects dans le texte rendu des tableaux de bord ; contrôle des graphiques (séries numériques, état vide).
5. **Absence de données codées en dur** : relecture des vues modifiées (aucune valeur métier écrite dans un gabarit ; l'exercice affiché est lu en base).

## 2. Résultats

| Vue | Données contrôlées | Résultat | Preuve |
|---|---|---|---|
| `/besoins` | 36 expressions de besoin, montant estimé | **36/36 concordants** | 8 profils |
| `/engagements` | 32 engagements, montant total | **32/32** | 8 profils |
| `/depense/liquidation` | 27 liquidations | **27/27** | 8 profils |
| `/depense/ordonnancement` | 21 ordonnancements | **21/21** | 8 profils |
| `/depense/paiement` | 22 paiements | **22/22** | 8 profils |
| `/budget` | 50 lignes : ouvert, engagé, disponible | **50/50** | 8 profils |
| Exports xlsx de la chaîne | 138 dossiers | **138/138**, montants exacts | transaction annulée |
| `/dashboard`, `/budget`, `/depense-cockpit` | ouvert 40 305 795 803 · engagé 68 311 725 · liquidé 51 466 725 · ordonnancé 41 431 725 · payé 28 581 725 · disponible 40 237 484 078 | **identiques à la base** | recalcul indépendant, écart 0,00 |
| `/reporting/cockpit` | entonnoir de la chaîne, exécution par axe (Σ des 21 axes + Hors PAP = ouvert global), décaissements mensuels | **concordant après correction** (répartition par axe, paiements datés par la validation à défaut de date de paiement, alertes alignées) | cockpit recalculé et comparé |
| `/tableaux-bord` | indicateurs, feux, nature, recettes | **concordant** ; « NaN » remplacé par un état vide explicite ; contributions issues d'un appel de fonds d'essai (**à arbitrer**) | `nan.mjs` : 0 occurrence après correction |
| `/strategie/dashboard/decisionnel` | score de pilotage, cohérence | **juste après correction** : référentiel vide → « non évaluable » au lieu de 75 % | tests P4 |
| `/workflow/dashboard` | délais, SLA, retours, blocages | calculés sur les dates réelles des tâches (création → fin) | tests workflow |
| En-tête (toutes les vues) | exercice et devise | lus en base (`Exercice::courant()`) | — |
| Notifications (cloche, boîte de réception) | compteur de non-lues, actions | compteur = notifications non lues en base | tests notifications |

## 3. Écarts relevés et traitement

| Écart | Cause | Traitement |
|---|---|---|
| Montant payé surévalué de 780 000 dans les rapports et notifications | paiement rejeté par la banque compté comme payé | portée `Paiement::regles()` appliquée partout (corrigé) |
| Réservation ENG-2026-0013 : 2 000 000 au lieu de 1 200 000 | dégagement partiel sans réduction de la réservation | code corrigé ; donnée réconciliée et journalisée (une opération) |
| Cockpit : une seule barre « — » de 40,3 Md | enveloppes `ACT-{axe}` non rattachées | rattachement à l'axe (corrigé) |
| Décaissements mensuels à 0 | aucune date de paiement saisie (8 paiements) | repli sur la date de validation ; **saisie des dates à arbitrer** |
| Graphiques absents sur 4 tableaux de bord, « NaN » sur `/tableaux-bord` | initialisation avant chargement de la bibliothèque ; apostrophe non échappée | corrigé |
| Déséquilibre budgétaire de 200 003 XAF | donnée source | **non corrigé** (montant officiel) ; à arbitrer |
| Contributions des États membres issues de AF-SPA-NAV-1 | appel de fonds d'essai | **non supprimé** ; à arbitrer |

## 4. Limites

- **Cloisonnement** : aucune portée d'accès n'est définie (`user_access_scopes` vide). Tous les profils voient donc les mêmes listes, ce qui explique des concordances identiques sur les 8 profils. Le filtrage est en place dans le code (`AccessScopeService::filtrerDossiersDepense`) ; la définition des portées relève d'un arbitrage.
- **Profils administrateur et commissaire** : connexion navigateur impossible sans configurer la double authentification, ce qui écrirait dans `users`. Leurs vues ont été rendues côté serveur dans une transaction annulée (contrôle d'affichage), sans relecture cellule par cellule.
- Les vues hors chaîne de la dépense et hors tableaux de bord sont classées « données non vérifiées » dans `MATRICE_CONFORMITE_VUES.md` : elles s'affichent sans erreur, mais leurs valeurs n'ont pas été comparées une à une à la base.
