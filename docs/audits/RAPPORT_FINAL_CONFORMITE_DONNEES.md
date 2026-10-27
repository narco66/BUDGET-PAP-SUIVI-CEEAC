# Rapport final de conformité des données — BUDGET-CEEAC / GESBUDEP

**Date :** 26 octobre 2026 · **Base :** PostgreSQL 18.4 `gest_budget_pap` (local, 127.0.0.1:5433) · **Exercice :** 2026.

## 1. Verdict par exigence

| Exigence | Verdict | Preuve |
|---|---|---|
| Données affichées issues de PostgreSQL, sans valeur fictive ni codée en dur | ✅ **Conforme** (2 exercices en dur corrigés) | analyse statique et provenance des pages clés (`AUDIT_PAGES_ET_COMPOSANTS.md`) |
| Unicité des référentiels et des dossiers | ✅ **Conforme** : aucun doublon illégitime | `RAPPORT_UNICITE_REFERENTIELS.md`, `donnees/unicite.json` |
| Exactitude des compteurs budgétaires | ✅ **Conforme** : 0 écart sur 50 lignes × 4 compteurs | `AUDIT_CALCULS_BUDGETAIRES.md`, `donnees/calculs_apres.json` |
| Cohérence PAP → Budget → EB → ENG → LIQ → ORD → PAY | ✅ **Conforme après correction** (1 anomalie corrigée) | `AUDIT_COHERENCE_CHAINE_DEPENSE.md` |
| Concordance affichage, exports et base | ✅ **Conforme** sur le périmètre vérifié (listes, exports, tableaux de bord de la chaîne, 8 profils) | `MATRICE_CONCORDANCE_DONNEES.md`, `RAPPORT_CONTROLE_DONNEES_VUES.md` |
| Agrégats et tableaux de bord | ✅ **Conforme après correction** (exécution par tâche, axes, décaissements, alertes, indicateurs sans base) | idem |
| Cloisonnement des données par périmètre | ⚠️ **Non conforme en l'état des données** : aucun périmètre défini, donc accès global. Mécanisme prêt et testé. | A1 |
| Équilibre du budget | ⚠️ **Écart de 200 003 XAF** dans la donnée officielle, non modifiée | A2 |
| Données d'essai | ⚠️ **1 appel de fonds probablement d'essai** (AF-SPA-NAV-1), conservé | A3 |
| Intégrité des données protégées, du journal et des historiques | ✅ **Conforme** : empreintes identiques ; journal et workflows en ajouts seuls | `RAPPORT_INTEGRITE_AVANT_APRES.md` |
| Respect des interdictions (aucune suppression, aucun reset, aucune modification d'habilitation ou de montant officiel) | ✅ **Respecté** | empreintes ; aucune commande destructive ; garde-fou des tests |

## 2. Actions réalisées

- 15 corrections de code, toutes couvertes par un test automatisé ou par un contrôle au navigateur (`RAPPORT_CORRECTIONS_DONNEES.md`).
- 1 correction de donnée technique (réservation de crédits #100 alignée sur son imputation), exécutée par commande avec simulation préalable et journalisée avant/après.
- Outils rejouables ajoutés : `audit_unicite_referentiels.php`, `audit_calculs_budgetaires.php`, `empreintes_donnees_protegees.php`, `empreinte_historique_workflow.php`, `inventaire_vues.php`, `depense:reconcilier-reservations`.

## 3. Effets de bord des travaux d'audit (déclarés)

- 4 documents GED créés automatiquement par des téléchargements pendant les parcours : #8 (modèle PAP), #15, #18, #19 (rapport financier des recettes en Word). Ils sont conservés, puisque supprimer un document GED est interdit. Exclusions des parcours renforcées.
- Journal d'audit : consultations et refus d'accès des parcours, ajoutés normalement.

## 4. Décisions attendues

Voir le tableau des points ouverts A1 à A8 dans `AUDIT_GLOBAL_DONNEES_BUDGET_CEEAC.md`. Les plus importantes sont le cloisonnement (A1), l'équilibre budgétaire (A2) et les droits du module stratégie (A6). Aucune n'a été exécutée sans autorisation.
