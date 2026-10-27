# 05 — Tests automatisés et recette du module « Mes Tâches »

*Date : 25/10/2026.*

## 1. Tests automatisés — `tests/Feature/MesTachesTest.php`

Exécution sur base de test isolée (SQLite en mémoire, `RefreshDatabase`), jamais sur la base PostgreSQL de travail.

```
php artisan test tests/Feature/MesTachesTest.php
→ 18 tests, 18 réussis, 81 assertions (53 s)
```

| # | Test | Exigences couvertes |
|---|---|---|
| 1 | `test_rec01_soumission_eb_cree_une_tache_pour_le_directeur` | REC-01, RG-01, RG-02 |
| 2 | `test_rec02_rec13_validation_clot_la_tache_et_affecte_l_etape_suivante` | REC-02, REC-13, RG-06 |
| 3 | `test_renvoi_depuis_l_etape_2_ne_bloque_plus_le_circuit` | A4, A5 |
| 4 | `test_rec10_utilisateur_non_habilite_ne_peut_ni_voir_ni_decider` | REC-10, RG-04, RG-05 |
| 5 | `test_l_administrateur_n_est_pas_place_dans_la_file_de_travail` | § 6.1 |
| 6 | `test_l_initiateur_titulaire_du_role_ne_voit_pas_son_propre_dossier_a_traiter` | § 17.2 SoD |
| 7 | `test_rec12_deux_decisions_sur_la_meme_tache_une_seule_acceptee` | REC-12, RG-07, RG-14 |
| 8 | `test_decision_api_obsolete_renvoie_409_et_idempotence` | RG-14, RG-19 |
| 9 | `test_prise_en_charge_exclusive_et_liberation` | § 6.2 groupe, § 6.4 |
| 10 | `test_suspension_bloque_la_decision_jusqu_a_la_reprise` | § 5.3 |
| 11 | `test_rec11_delegation_valide_autorise_et_delegation_expiree_refuse` | REC-11, RG-12 (titulaire `pour_compte_de`) |
| 12 | `test_rec16_rg11_notification_lue_sans_effet_sur_la_tache` | REC-16, RG-11 |
| 13 | `test_rec07_rec08_ordonnancement_affecte_selon_le_seuil` | REC-07, REC-08, RG-16 |
| 14 | `test_v1_controle_du_rib_non_contournable_par_l_ecran_blade` | V1, RG-09 |
| 15 | `test_rec09_paiement_partiel_reliquat_affiche_sur_la_fiche` | REC-09, RG-17 |
| 16 | `test_rg15_statistiques_et_export_limites_au_perimetre` | REC-15, REC-18, RG-15 |
| 17 | `test_pages_blade_et_api_listes` | Pages et API |
| 18 | `test_commande_de_reprise_idempotente` | REC-17 (partiel), RG-19 |

## 2. Recette sur PostgreSQL 18 (base réelle, transaction annulée)

Le script de recette ouvre une transaction, exécute un parcours complet via les vraies routes HTTP avec les comptes de démonstration, puis **annule la transaction** : aucune donnée n'est conservée (212 tâches dont 6 ouvertes avant et après). Seules les séquences PostgreSQL avancent (comportement normal, sans effet métier).

| Contrôle | Résultat | Détail |
|---|---|---|
| REC-01 tâche créée à la soumission d'une EB PAP | OK | Tâche affectée au rôle directeur |
| Tâche visible dans « Mes tâches » du directeur | OK | |
| Tâche absente de la file du comptable | OK | |
| Prise en charge | OK | HTTP 200, statut `en_cours` |
| Décision avec version obsolète | OK | HTTP 409 |
| REC-02 décision du directeur | OK | Tâche `terminee` |
| Étape suivante affectée | OK | Secrétaire général |
| REC-12 seconde décision sur la tâche close | OK | HTTP 422 « déjà traitée » |
| Renvoi depuis l'étape 2 | OK | |
| Retour au directeur (nouvelle tâche) | OK | Nouvelle référence, ancienne tâche conservée |
| Étape 2 de nouveau traitable | OK | Blocage du circuit corrigé |
| EB approuvée, engagement généré | OK | |
| REC-14 historique complet | OK | 9 événements : création, prise en charge, décisions, créations |
| Notification cliquable vers la tâche | OK | `/mes-taches/{id}` |
| Page Blade « Mes tâches » | OK | |
| Fiche de tâche après décision | OK | Chronologie présente |
| Supervision (SG) | OK | |

**Résultat : 17/17.** Rejouée le 25/10/2026 après la dernière modification du code.

## 3. Contrôle visuel (Chrome, base PostgreSQL)

Captures réalisées connecté en directeur, via le protocole DevTools :

- Liste `/mes-taches` : en-tête avec heure d'actualisation, indicateurs (3 à traiter, 59 terminées sur la période), onglets, filtres, tableau des tâches avec badges de module, de priorité et de statut.
- Fiche `/mes-taches/1` : bannière (EB-2026-0001, étape 1, Validation DRHMG), dossier, imputation avec crédit, engagé et disponible, pièces, chronologie, actions (Prendre en charge, Valider, Retourner, Rejeter, Suspendre, Observation).
- Charte de l'application respectée (barre latérale bleue, cartes, badges), aucune ressource bloquée par la politique CSP.

## 4. Cas du plan de recette (§ 22)

| Cas | Statut | Moyen |
|---|---|---|
| REC-01, 02, 07, 08, 09, 10, 11, 12, 13, 14, 15, 16, 18 | Vérifiés | Tests automatisés et/ou recette PostgreSQL ci-dessus |
| REC-03, 04, 05, 06 | Vérifiés | Tests existants `ChaineDepenseEbConformiteTest`, `ChaineDepenseAvalConformiteTest`, `DepenseTest` (circuits inchangés, rejoués dans la non-régression, rapport 06) |
| REC-17 Incident asynchrone | Partiel | Reprise idempotente et index unique testés ; pas de test d'injection de panne en file asynchrone |
| REC-19 Seeding de démonstration | Vérifié | Aucun seeder exécuté ; empreintes identiques (rapport 07) |
| REC-20 Conformité visuelle | Non vérifiable | Aucune maquette Figma dans `/docs/` |

## 5. Non réalisé

- Tests de bout en bout navigateur outillés (Playwright) : non installés dans le projet, remplacés par les captures Chrome et les tests HTTP.
- Test de charge (cible p95 < 2 s, § 19) : non exécuté.
- Audit d'accessibilité outillé : non exécuté.
