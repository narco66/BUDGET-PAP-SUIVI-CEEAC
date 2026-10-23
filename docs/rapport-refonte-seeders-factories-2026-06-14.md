# Rapport de refonte des seeders et factories - 2026-06-14

## Perimetre traite

- Ajout d'un seeder de demonstration institutionnelle idempotent : `Database\Seeders\Demo\InstitutionalShowcaseSeeder`.
- Ajout d'un orchestrateur lisible : `Database\Seeders\DemoDataSeeder`.
- Integration du seeder dans `SafeDatabaseSeeder`, apres les referentiels institutionnels et utilisateurs.
- Normalisation de `UserFactory` avec des profils CEEAC realistes et un etat `institutional()` pour rattacher role et structure.
- Ajout de factories metier prioritaires : structure, PAP, budget, produit, sous-produit, activite, tache, source de financement, ligne budgetaire, expression de besoin et engagement.
- Correction du seeder volumetrique de chaine de depense pour respecter strictement le disponible budgetaire par ligne.

## Corrections appliquees

- Donnees de reference CEEAC enrichies : exercices 2024-2027, sources de financement internes/PTF, organigramme Commission, profils utilisateurs, PAP, budget, RBM/GAR, activites, taches, indicateurs et mesures.
- Alignement strict sur les enums MySQL existants :
  - `sources_financement.type` : `interne_ceeac`, `externe_ptf`.
  - `structures.type` : utilisation de `cellule` pour les cabinets.
  - `indicateurs.type` : `operationnel`.
  - `activites.statut` : `actif`.
  - `mesures.statut` : `validee`.
- Respect des contraintes de credits :
  - `montant_paye <= montant_ordonnance <= montant_liquide <= montant_engage <= montant_ouvert`.
  - Conservation des compteurs d'execution existants lors des re-seedings.
  - Plafonnement des imputations bulk au disponible reel de chaque ligne.
- Donnees non destructives : usage de `updateOrCreate`, `firstOrCreate`, transactions et gardes d'idempotence.
- Factories RBM/PAP/budget alignees sur des libelles institutionnels plausibles au lieu de phrases generiques.
- Exposition de `HasFactory` sur les modeles metier concernes pour permettre les tests unitaires et d'integration.

## Anomalies detectees

- Les valeurs initiales `interne`, `ptf`, `cabinet`, `resultat`, `termine` et `valide` n'etaient pas compatibles avec le schema MySQL effectif.
- Le seeder institutionnel remettait des compteurs budgetaires a leur valeur initiale au second passage, ce qui cassait l'idempotence apres generation de dossiers de depense.
- Le seeder bulk pouvait affecter a la derniere imputation un montant superieur au disponible de sa ligne.
- Les factories existantes ne couvraient pas le graphe complet `Pilier -> Axe -> Produit -> Sous-produit -> Activite -> Tache`.
- Plusieurs modeles metier n'exposaient pas `HasFactory`, rendant impossible l'utilisation directe de factories Laravel.

## Verifications

- `php -l database\seeders\Demo\InstitutionalShowcaseSeeder.php` : OK.
- `php -l database\seeders\Demo\ChaineDepenseBulkSeeder.php` : OK.
- `php -l database\factories\UserFactory.php` : OK.
- `php -l database\seeders\SafeDatabaseSeeder.php` : OK.
- `php artisan db:seed --class=Database\Seeders\Demo\InstitutionalShowcaseSeeder` : OK, rejoue sans doublon.
- `php artisan db:seed --class=Database\Seeders\DemoDataSeeder` : OK.
- `php artisan test tests\Feature\FactoriesDemoGraphTest.php` : OK.
- `php artisan test tests\Feature\DonneesDemoTest.php` : OK.
- `php artisan test tests\Feature\BudgetTest.php` : OK.
- `php artisan test tests\Feature\PapP0Test.php` : OK.

## Recommandations restantes

- Remplacer progressivement les usages aleatoires du seeder bulk par des sequences deterministes si les demonstrations doivent produire exactement les memes totaux d'un environnement a l'autre.
- Ajouter des factories specialisees pour `LigneBudgetaire`, `Activite`, `Indicateur` et `Mesure`, afin d'eviter que les tests creent des objets incomplets.
- Ajouter un audit applicatif dedie aux donnees demo pour signaler les doublons fonctionnels, les lignes sans consommation et les objets RBM/PAP non relies.
