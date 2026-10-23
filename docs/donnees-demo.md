# Données de démonstration — BUDGET-PAP-SUIVI-CEEAC

Ce document décrit le système de génération de **données de test/démonstration** : un jeu
de données institutionnel CEEAC, cohérent et exploitable dans tous les modules
(tableaux de bord, rapports, chaîne de la dépense, GAR/RBM, RBAC), produit **sans jamais
écraser ni supprimer** les données déjà présentes.

## 1. Principe : non destructif et idempotent

- **Aucune** opération destructive n'est utilisée : pas de `truncate`, `delete`, `db:wipe`,
  `migrate:fresh` ni `migrate:refresh`.
- Toutes les insertions sont **gardées** : `firstOrCreate`, `updateOrCreate`, `exists()`.
  Relancer le seeding ne crée aucun doublon et ne modifie pas l'existant.
- Les **clés étrangères** et l'**ordre d'insertion** (référentiels → démonstration) sont respectés.
- Les **invariants budgétaires** sont garantis par construction (voir §4).

## 2. Fichiers

| Fichier | Rôle |
|---|---|
| `database/migrations/2026_06_12_000001_create_parametres_table.php` | Table `parametres` (clé/valeur typée). |
| `app/Models/Parametre.php` | Modèle + accès typé `Parametre::valeur($cle, $defaut)`. |
| `app/Services/Demo/DemoChaineBuilder.php` | Service générant un dossier de dépense de bout en bout à invariants stricts. |
| `database/seeders/Demo/InstitutionDemoSeeder.php` | Structures et fonctions complémentaires de l'organigramme. |
| `database/seeders/Demo/UsersDemoSeeder.php` | 13 utilisateurs (un par rôle), rôles + profils + rattachements. |
| `database/seeders/Demo/IndicateursDemoSeeder.php` | Indicateurs stratégiques/opérationnels + mesures (feux GAR). |
| `database/seeders/Demo/ChaineDepenseDemoSeeder.php` | 10 dossiers couvrant tous les cas de la chaîne de la dépense. |
| `database/seeders/Demo/SettingsDemoSeeder.php` | Paramètres système (institution, budget, sécurité, workflow). |
| `database/seeders/SafeDatabaseSeeder.php` | Orchestrateur non destructif (référentiels puis démonstration). |
| `app/Console/Commands/SeedDemoCommand.php` | Commande `budgetpap:seed-demo --safe`. |
| `tests/Feature/DonneesDemoTest.php` | Tests : cohérence, invariants, non-destruction, idempotence. |

## 3. Exécution

### Commande recommandée

```bash
php artisan budgetpap:seed-demo --safe
```

La commande :
1. refuse l'exécution en **production** (sauf `--force`) ;
2. affiche un **état avant** (comptages) ;
3. demande confirmation en mode interactif ;
4. exécute `SafeDatabaseSeeder` dans l'ordre des dépendances ;
5. affiche un **rapport final** (avant / après / ajoutés) et le total des crédits ouverts/engagés ;
6. en cas d'erreur, **interrompt sans altérer** l'existant (insertions idempotentes).

### Équivalent direct (non destructif)

```bash
php artisan db:seed --class=SafeDatabaseSeeder
```

### Ordre d'insertion

1. **Référentiels** (idempotents) : `RoleSeeder`, `ProfilSeeder`, `PosteSeeder`, `StructureSeeder`,
   `ExerciceSeeder`, `SourceFinancementSeeder`, `EtatMembreSeeder`, `RecetteSeeder`,
   `NomenclatureBudgetaireSeeder`, `Pap2026Seeder`, `Budget2026Seeder`, `WorkflowSeeder`.
2. **Démonstration** : `InstitutionDemoSeeder`, `UsersDemoSeeder`, `IndicateursDemoSeeder`,
   `SettingsDemoSeeder`, `ChaineDepenseDemoSeeder`.

> ⚠️ Ne jamais utiliser `migrate:fresh --seed` sur une base contenant des données réelles.
> Pour appliquer la seule migration `parametres` : `php artisan migrate` (non destructif).

## 4. Données produites

### Utilisateurs (13)
Un compte par rôle, courriel `<role>@ceeac.test`, mot de passe `password` :
`administrateur`, `secretaire_general`, `commissaire`, `directeur`, `chef_service`,
`gestionnaire_budgetaire`, `controleur_financier`, `ordonnateur`, `comptable`,
`auditeur`, `point_focal`, `consultant`, `lecteur`.

### Chaîne de la dépense (10 dossiers)
Couvre l'ensemble des cas métier :

| Dossier | Cas illustré |
|---|---|
| D001 | Bon de commande — chaîne complète, **payé en totalité** |
| D002 | Mission — **paiement partiel** |
| D003 | **Besoin rejeté** (aucun engagement) |
| D004 | Marché — **multi-imputations**, liquidation partielle, **paiement en attente** |
| D005 | Convention — **engagement visé** sans liquidation (service fait à venir) |
| D006 | **Engagement provisionnel soumis** (en attente de visa) |
| D007 | **Engagement annulé** (crédits non réservés) |
| D008 | Travaux — engagement **partiellement** liquidé puis payé |
| D009 | Carburant — chaîne complète, payé |
| D010 | Assurances — **dossier retourné / rejeté** |

Chaque dossier porte des **pièces justificatives** (métadonnées uniquement, aucun fichier
physique : chemin `demo/...pdf`).

### Invariants garantis (par `DemoChaineBuilder`)
- `Σ imputations = montant_total` de l'engagement ;
- crédits réservés **uniquement** aux statuts `soumis`/`vise` (annulé/rejeté : non réservés) ;
- pour chaque ligne budgétaire :
  `montant_payé ≤ montant_ordonnancé ≤ montant_liquidé ≤ montant_engagé ≤ montant_ouvert` ;
- répartition **au prorata** des imputations (reliquat sur la dernière) ;
- paiement au statut `soumis` = **en attente** (compteur `montant_paye` non appliqué).

## 5. Tests

```bash
php artisan test tests/Feature/DonneesDemoTest.php
```

Vérifie : chaque utilisateur a un rôle ; chaque rôle a des permissions ; chaque ligne est
rattachée à la nomenclature ; chaque activité à la chaîne GAR/RBM ; chaque engagement a une
imputation ; `Σ imputations = montant_total` ; les invariants de compteurs ; la présence des
cas métier (rejeté, annulé, multi-imputations, paiement en attente/validé) ; le caractère
**non destructif** (témoin préservé) et **idempotent** (empreinte identique au 2ᵉ passage).

## 6. Checklist de validation fonctionnelle

- [ ] Connexion avec `gestionnaire_budgetaire@ceeac.test` / `password`.
- [ ] **Tableau de bord** : taux d'exécution > 0, crédits engagés ≈ 205 000 000 XAF.
- [ ] **Budget** : 35 lignes, compteurs ouvert/engagé/liquidé/ordonnancé/payé cohérents.
- [ ] **Dépense** : 10 besoins, 9 engagements, 5 liquidations/ordonnancements/paiements.
- [ ] **GAR/RBM** : indicateurs avec feux (vert/orange/rouge) et mesures T1 2026.
- [ ] **Rapports** : exports PDF/Excel alimentés.
- [ ] **Re-run** `php artisan budgetpap:seed-demo --safe` : rapport « Ajoutés = +0 » partout.
