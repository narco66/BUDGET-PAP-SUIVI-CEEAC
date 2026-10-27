# Rapport des tests Playwright — parcours réel de l'application

**Date :** 25 octobre 2026 · **Outil :** Playwright 1.48.2 (`playwright-core`), qui pilote le Chrome installé sur le poste. Il est installé dans un dossier isolé, hors des dépendances du projet. **Cible :** http://budget-pap-suivi-ceeac.test (Laragon, Apache), vers PostgreSQL 18 local.
**Scripts (dossier de travail de la session) :** `parcours.mjs` (parcours par rôle), `concordance.mjs` (valeurs affichées ↔ base), `graphiques.mjs` (rendu des graphiques), `capture_*.mjs` (captures), `nan.mjs`.

## 1. Démarrage et environnement vérifiés

| Élément | Constat |
|---|---|
| PHP / Laravel / Node | 8.3.10 / 13.15.0 / 22.12.0 ; frontend Blade + Vite 8 (SPA Vue 3 séparé) |
| PostgreSQL | 18.4, `gest_budget_pap` sur 127.0.0.1:5433, instance locale (vérifiée par `inet_server_addr/port`) |
| Disponibilité | `/login` 200, `/health` 200, serveur Vite 200 |
| Authentification | comptes de démonstration (un par rôle) ; MFA de démonstration sur défi ; **aucune configuration MFA effectuée** (elle écrirait sur l'utilisateur) |
| Exclusions du parcours | liens qui écrivent : exports et modèles (archivage GED), ouverture de notification, MFA, sortie de session |

## 2. Parcours par rôle (menus, puis un niveau de liens internes)

| Rôle | Pages | Entrées de menu | Codes HTTP |
|---|---|---|---|
| directeur | 250 | 75 | 200 : 241, 403 : 7, 500 : 1, délai : 1 |
| chef_service | 250 | 73 | 200 : 241, 403 : 7, délai : 2 |
| chef_service_mg | 150 | 67 | 200 : 147, délai : 3 |
| controleur_financier | 250 | 67 | 200 : 247, 403 : 1, délai : 2 |
| agent_comptable | 250 | 67 | 200 : 248, 403 : 1, délai : 1 |
| secretaire_general | 250 | 67 | 200 : 243, 403 : 1, délai : 6 |
| president | 250 | 67 | 200 : 248, 403 : 1, délai : 1 |
| gestionnaire_budgetaire | 250 | 66 | 200 : 249, 403 : 1 |
| auditeur | 250 | 71 | 200 : 245, 403 : 1, délai : 4 |
| lecteur | 250 | 66 | 200 : 248, 403 : 1, délai : 1 |
| administrateur, commissaire | — | — | connexion impossible au navigateur : MFA obligatoire non configurée. **Administrateur couvert côté serveur** : 329 routes GET, 0 erreur 500 (transaction annulée). |

**Total : 2 400 pages visitées, 255 chemins distincts.**

## 3. Défauts détectés et suite donnée

| Défaut | Pages | Cause | Statut |
|---|---|---|---|
| Graphiques non rendus (`ApexCharts is not defined`) | `/dashboard`, `/tableaux-bord`, `/reporting/cockpit`, `/paps/pilotage` | script de graphique exécuté avant le module Vite différé | ✅ corrigé : 14 graphiques rendus, 0 erreur (contrôle `graphiques.mjs`) |
| Liens du menu GED menant à un 403 | `/ged/categories`, `types`, `workflows`, `conservation`, `journal`, `documents/create` | droits du menu (`ged.voir`) ≠ droits des pages (`ged.admin`, `ged.consulter_journal`, `ged.creer`) | ✅ corrigé (menu, onglets GED, bouton du hub RBM) |
| HTTP 500 aléatoires | `/contrats`, `/depense/liquidation` | `.env` lu vide sous requêtes concurrentes (Apache thread-safe) ; risque de connexion à une autre base (port 5432) | ✅ corrigé (`Env::disablePutenv`) ; 0 erreur sur 180 requêtes concurrentes |
| « NaN » affiché | `/tableaux-bord` (5 profils) | anneau des feux avec 0/0/0 | ✅ corrigé (message explicite) |
| Délais de chargement > 45 s | `/rbm/arbre/1` (4 profils) | droit `rbm.action` évalué ~2 300 fois à 8,3 ms | ✅ corrigé : 20,8 s → 3,0 s (auditeur), 1,3 s (administrateur) ; droits identiques (18 tests RBM verts) |
| Autres délais isolés | `/budget`, `/exercices`, `/nomenclature/*`, `/partenaires`, `/sources`, `/reporting`… | charge du poste (suites de tests exécutées en parallèle) ; pages en 200 en requête isolée | sans suite |

## 4. Concordance des valeurs affichées (8 profils)

Les 8 profils obtiennent **100 % de concordance** sur toutes les listes : EB 36/36, ENG 32/32, LIQ 27/27, ORD 21/21, PAY 22/22, lignes budgétaires 50/50. Les totaux des tableaux de bord sont identiques à la base (détail : `MATRICE_CONCORDANCE_DONNEES.md`).

## 5. Cloisonnement

Selon la règle actuelle de l'application, l'absence de périmètre défini (`user_access_scopes` vide) vaut accès global. **Tous les profils voient donc les 138 dossiers**, y compris les profils organiques : le directeur voit 133 dossiers hors de son sous-arbre organique, le chef de service MG 122. Le mécanisme de filtrage des listes ENG, LIQ, ORD et PAY a été ajouté et testé ; l'activation du cloisonnement relève d'un arbitrage (voir le rapport final).

## 6. Limites

- Pas de tests visuels de non-régression (comparaison d'images).
- Administrateur et commissaire non parcourus au navigateur, à cause de la MFA.
- Le SPA Vue n'est pas parcouru (l'application principale est en Blade).
- Les exports n'ont pas été cliqués au navigateur (archivage GED automatique) : ils ont été vérifiés côté serveur, dans une transaction annulée.
- **Effet de bord à signaler :** au premier parcours, le profil directeur a téléchargé le modèle d'import PAP (`/budget/modele/pap`). L'application l'a archivé automatiquement en GED (document `CEEAC/GED/EXPORT/DOCGEN/2026/000001`). Il n'a pas été supprimé, conformément à la consigne ; les exclusions ont été élargies pour les passages suivants.
