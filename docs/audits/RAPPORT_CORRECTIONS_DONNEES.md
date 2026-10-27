# Rapport des corrections — audit global des données

**Date :** 25 octobre 2026 · **Principe :** corriger le code et les règles ; ne jamais modifier un montant officiel pour masquer une incohérence. Une seule donnée a été ajustée, sur une table de suivi technique, avec journalisation avant/après.

## 1. Corrections du code

| # | Défaut | Correction | Fichiers | Test |
|---|---|---|---|---|
| C1 | Dégagement partiel sans réduction de la réservation de crédits | réduction (et libération si soldée) de la réservation active | `app/Services/EngagementService.php` | `AuditDonneesCorrectionsTest::test_degagement_partiel…` |
| C2 | Paiement rejeté par la banque compté comme payé dans des états | portée `Paiement::regles()` ; appliquée à l'audit dépense, au reporting mensuel, à Tompro, à la notification de paiement partiel, à l'exécution par tâche | `app/Models/Paiement.php`, `DepenseAuditService`, `ReportingService`, `TomproService`, `DepenseNotificationService`, `DepenseReportingService` | `…test_paiement_a_instrument_rejete…` |
| C3 | Exécution par tâche multipliée par les paiements partiels | une agrégation par étape | `app/Services/Depense/DepenseReportingService.php` | `…test_execution_par_tache…` |
| C4 | Listes ENG/LIQ/ORD/PAY sans filtrage de périmètre | `AccessScopeService::filtrerDossiersDepense` (web et API) ; sans effet pour un accès global | `AccessScopeService`, `EngagementController`, `EtapeDepenseController`, API `EngagementApiController`, `EtapeDepenseApiController` | `…test_listes_de_la_depense_respectent_le_perimetre…` |
| C5 | Exercice codé en dur (en-tête, formulaire EB) | `Exercice::courant()` / exercice du dossier | `app/Models/Exercice.php`, `layouts/app.blade.php`, `besoins/_form.blade.php` | `…test_en_tete_affiche_l_exercice_actif…` |
| C6 | Faux positif « liaisons désynchronisées » ; réconciliation écrasant les enveloppes partagées | règle d'enveloppe PAP (`ACT-%`) dans la détection, l'audit PAP, la synchronisation et la simulation `budget:reconcilier` | `StructureBudgetCoherenceService`, `PapAuditService`, `RbmBudgetSyncService`, `BudgetReconcilierCommand` | `…test_enveloppe_pap_partagee…` |
| C7 | Graphiques non rendus (4 tableaux de bord) | initialisation après les modules Vite | `dashboard`, `tableaux/index`, `reporting/cockpit`, `paps/dashboard` (vues) | vérification Playwright (14 graphiques) |
| C8 | `.env` lu vide sous requêtes concurrentes (500, connexion possible à une autre base) | `Env::disablePutenv()` | `bootstrap/app.php` | 180/180 requêtes concurrentes OK ; garde-fou des tests |
| C9 | Tests pouvant viser la base de l'application | refus d'exécution hors SQLite en mémoire | `tests/TestCase.php`, `tests/Unit/EnvironnementDeTestTest.php` | `EnvironnementDeTestTest` |
| C10 | Menu GED menant à des 403 | droits du menu et des onglets alignés sur les contrôleurs | `config/menu.php`, `ged/_nav-module.blade.php`, `rbm/hub.blade.php` | parcours Playwright |
| C11 | « NaN » dans les graphiques sans données | message explicite, valeurs numériques | `tableaux/index.blade.php` | contrôle Playwright |
| C12 | Cache de cockpit jamais réutilisé (collections refusées à la désérialisation) | stockage en types natifs, lecture unique | `app/Support/CockpitCache.php` | suites cockpit |
| C13 | Cockpit : axes, décaissements, alertes incohérentes | voir le rapport Cockpit (même session) | `ReportingService`, vue cockpit | capture et tests |
| C14 | Arbre RBM en 20,8 s | droits RBM mémorisés par requête ; droits invariants calculés une fois | `RbmAuthorization`, `rbm/_actions`, `rbm/arbre` | 18 tests RBM (MFA désactivée) |
| C15 | Pilotage stratégique : cohérence à 75 % sur référentiel vide, matrice comptée deux fois | contrôles évaluables seulement, score décomposé | services stratégie, vues et PDF | `PlanificationStrategiquePhaseP4Test` |

## 2. Correction de données (une seule)

| Donnée | Avant | Après | Méthode | Retour arrière |
|---|---|---|---|---|
| `reservations_credits` #100 (ENG-2026-0013, ligne 37) | 2 000 000, active | 1 200 000, active | `php artisan depense:reconcilier-reservations` (simulation, puis `--appliquer`) | valeurs avant/après dans `journaux_audit` (`reservation_credits_reconciliee`) |

Aucun montant de ligne budgétaire ni de dossier n'a été modifié (empreintes identiques).

## 3. Non corrigé — arbitrage métier requis

1. **Cloisonnement** : `user_access_scopes` est vide. Avec la règle « pas de périmètre = accès global », tous les profils voient tous les dossiers. Le mécanisme existe (C4) ; il faut définir les périmètres des profils organiques (affectations), ce qui modifie des habilitations et requiert une autorisation explicite.
2. **Équilibre du Budget 2026** : écart de 200 003 XAF entre dépenses et recettes prévues.
3. **Appel de fonds AF-SPA-NAV-1** : donnée d'essai probable qui alimente les indicateurs de recouvrement, non rattachée aux contributions.
4. **Paiements sans date d'exécution** (8/8).
5. **Codes de rubrique répétés** sous des paragraphes différents (nomenclature officielle).
6. **Droits du module stratégie** : `strategie.view` absent, donc module réservé à l'administrateur (seeder disponible, non exécuté).
7. **MFA** : l'administrateur et le commissaire doivent configurer leur MFA pour se connecter.

## 4. Commandes ajoutées

- `depense:reconcilier-reservations [--appliquer]`
- `database/scripts/audit_unicite_referentiels.php`
- `database/scripts/audit_calculs_budgetaires.php`
- `database/scripts/empreintes_donnees_protegees.php` (étendu)
- `database/scripts/empreinte_historique_workflow.php`
