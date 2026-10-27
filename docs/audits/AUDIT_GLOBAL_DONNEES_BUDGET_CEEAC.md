# Audit global des données — BUDGET-CEEAC / GESBUDEP

**Période :** 25-26 octobre 2026 · **Environnement :** poste local (Laragon, Apache, PHP 8.3, Laravel 13), PostgreSQL 18.4 `gest_budget_pap` sur 127.0.0.1:5433, vérifié comme instance locale · **Exercice :** 2026 (XAF).
**Principe :** lecture d'abord ; corrections dans le code et les règles ; aucune donnée officielle modifiée pour masquer un écart ; corrections de données risquées proposées, non exécutées.

## 1. Synthèse

| Axe | Résultat | Détail |
|---|---|---|
| Provenance | toutes les pages clés lisent PostgreSQL via les services ; aucune donnée fictive ni codée en dur dans l'affichage (2 exercices écrits en dur corrigés) | `AUDIT_PAGES_ET_COMPOSANTS.md` |
| Unicité | **aucun doublon illégitime** dans les référentiels et les dossiers ; 33 codes de rubrique répétés sous des paragraphes différents (nomenclature officielle, à confirmer) | `RAPPORT_UNICITE_REFERENTIELS.md` |
| Calculs budgétaires | **0 écart** sur les 50 lignes et les 4 compteurs (engagé 68 311 725 ; liquidé 51 466 725 ; ordonnancé 41 431 725 ; payé 28 581 725) ; 2 agrégations fausses corrigées (exécution par tâche, répartition par axe) | `AUDIT_CALCULS_BUDGETAIRES.md` |
| Cohérence de la chaîne | 1 anomalie sur 138 dossiers (réservation de ENG-2026-0013), corrigée dans le code et réconciliée ; paiement rejeté compté comme payé dans des états, corrigé | `AUDIT_COHERENCE_CHAINE_DEPENSE.md` |
| Concordance affichage ↔ base | **100 %** : listes EB 36, ENG 32, LIQ 27, ORD 21, PAY 22, budget 50 lignes, exports 138 dossiers, sur 8 profils | `MATRICE_CONCORDANCE_DONNEES.md` |
| Parcours réel | 2 400 pages visitées par 10 profils ; défauts trouvés : graphiques absents, menus en 403, erreurs 500 aléatoires, « NaN », arbre RBM à plus de 45 s. Tous corrigés. | `RAPPORT_TESTS_PLAYWRIGHT.md` |
| Intégrité | données protégées inchangées ; journal et historique des workflows en ajouts seuls | `RAPPORT_INTEGRITE_AVANT_APRES.md` |
| Corrections | 15 corrections de code, 1 correction de donnée technique journalisée | `RAPPORT_CORRECTIONS_DONNEES.md` |

## 2. Inventaire

217 tables, dont 96 non vides. Sources uniques de vérité : exercice (1), Budget 2026 (1 en-tête, 56 lignes dont 50 actives, 70 sous-lignes), nomenclature (11 / 38 / 119 / 224), organigramme (78 unités), habilitations (17 utilisateurs, 14 rôles, 392 permissions), PAP (6 piliers actifs, 21 axes, 71 produits, 213 sous-produits, 215 activités actives) et chaîne de la dépense (36 EB, 32 ENG, 27 LIQ, 21 ORD, 22 PAY). Détail : `INVENTAIRE_TABLES_REFERENTIELS.md`.

## 3. Défauts majeurs trouvés et corrigés

1. **Réservation de crédits non réduite au dégagement partiel** (ENG-2026-0013, 800 000 XAF) : code corrigé, donnée réconciliée par commande journalisée.
2. **Paiement rejeté par la banque compté comme payé** dans l'audit, le reporting, Tompro et une notification (780 000 XAF) : portée `Paiement::regles()`.
3. **Exécution par tâche multipliée** par les paiements partiels : agrégation par étape.
4. **Cockpit exécutif** : exécution par axe absente (enveloppes non rattachées), décaissements mensuels à 0, alertes contradictoires. Tout est corrigé.
5. **Erreurs 500 aléatoires** sous charge, avec risque de connexion à une autre base : `Env::disablePutenv()`.
6. **Tests capables de viser la base de l'application** : garde-fou bloquant (SQLite en mémoire exigé).
7. **Indicateurs trompeurs** : cohérence stratégique à 75 % sur un référentiel vide (désormais « non évaluable »), faux positif « 12 liaisons désynchronisées ».

## 4. Points ouverts (arbitrage requis, non exécutés)

| # | Sujet | Pourquoi non exécuté | Proposition |
|---|---|---|---|
| A1 | Cloisonnement : `user_access_scopes` vide, tous les profils voient les 138 dossiers | modifie des habilitations | définir les périmètres des profils organiques ; le filtrage est prêt (`AccessScopeService`) |
| A2 | Budget 2026 déséquilibré de 200 003 XAF (dépenses 40 305 795 803 ; recettes 40 305 595 800) | montant officiel | soumettre à la Direction du Budget |
| A3 | Appel de fonds AF-SPA-NAV-1 (essai probable) alimentant le recouvrement | suppression de donnée non prouvée illégitime | confirmer, puis archiver ou rattacher aux contributions |
| A4 | 8 paiements réglés sans date d'exécution | saisie métier | saisir les dates (repli actuel : date de validation) |
| A5 | 33 codes de rubrique répétés | nomenclature officielle | confirmer avec la Direction du Budget |
| A6 | Permission `strategie.view` absente (module stratégie réservé à l'administrateur) | modifie les permissions | exécuter `StrategiePermissionsSeeder` après accord |
| A7 | MFA non configurée pour l'administrateur et le commissaire | écrit dans `users` | configuration par les titulaires des comptes |
| A8 | Document généré archivé en GED à chaque téléchargement (doublons) | règle de conservation documentaire | dédoublonnage par empreinte, ou archivage des seuls documents officiels |
