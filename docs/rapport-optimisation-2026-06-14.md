# Rapport d'optimisation - 2026-06-14

## Perimetre traite

Audit cible de l'application Laravel/MySQL BUDGET-PAP-SUIVI-CEEAC sur les surfaces suivantes :

- securite de rendu HTML et templates e-mail ;
- coherence des listes budgetaires et de leurs totaux ;
- bornage des endpoints API volumineux ;
- verification ciblee des routes, tests et syntaxe PHP.

## Anomalies detectees et corrections appliquees

### Correctifs suite complete

- Le rejet PAP via l'ancien formulaire HTTP ne fournissait pas toujours `categorie_rejet`, alors que le service workflow l'exige. Une categorie documentaire par defaut est maintenant appliquee au niveau du controleur, sans assouplir l'exigence du service.
- La validation des plans strategiques dependait uniquement du workflow `STRATEGIE`; les tests et certains environnements minimalistes ne seedent pas ce circuit. Le controleur bascule maintenant sur `StrategieValidationService` lorsque le circuit n'est pas disponible ou absent.
- Le rejet de plan strategique accepte le champ historique `motif_rejet` en plus du champ workflow `observation`.
- La validation directe d'un encaissement saisi reste compatible avec les appels service, tout en conservant les controles justificatif et rubrique budgetaire.
- Le moteur workflow resout maintenant les validateurs institutionnels responsables, delegues et interims meme lorsqu'ils ne portent pas le role Spatie de l'etape.

### Securite applicative

- Des vues Blade utilisaient des sorties non echappees pour du HTML administrable ou evitable.
- Les variables injectees dans les templates e-mail etaient remplacees sans echappement prealable.
- Le manuel strategie convertissait le Markdown en autorisant le HTML brut.

Corrections :

- ajout de `App\Support\SafeHtml`, avec liste blanche de balises et attributs ;
- assainissement des corps HTML e-mail avant envoi ;
- echappement systematique des variables de templates e-mail ;
- suppression de sorties Blade non echappees dans les formulaires utilisateurs et la liste des structures ;
- conversion Markdown du manuel strategie avec HTML brut supprime.

### Budget et UX

- Les totaux de la page des lignes budgetaires etaient calcules sur le perimetre global de l'exercice, sans reprendre les filtres visibles de la liste.

Correction :

- centralisation des filtres de liste dans `LigneBudgetaireController` ;
- application des memes filtres aux lignes paginees et aux totaux.

### API et performance

- L'endpoint API des structures retournait toute la collection en une seule reponse.
- Le connecteur TOMPRO pouvait exporter tous les paiements valides sans limite explicite.

Corrections :

- pagination bornee de `/api/v1/structures`, 50 elements par defaut, 100 maximum ;
- validation des filtres TOMPRO ;
- limite TOMPRO de 200 paiements par defaut, 500 maximum ;
- tri explicite des paiements exportes par date de validation decroissante.

## Verifications executees

- `php -l` sur les fichiers PHP modifies ;
- `php artisan test tests\Unit\SafeHtmlTest.php` ;
- `php artisan test tests\Feature\BudgetTest.php tests\Feature\BudgetB0Test.php` ;
- `php artisan test tests\Feature\ApiTest.php tests\Feature\InteroperabiliteApiP0Test.php` ;
- `php artisan route:list` ;
- `php artisan view:cache` ;
- `npm install` puis `npm run build` ;
- `cmd /c npm audit --audit-level=critical`.
- `php artisan test tests\Feature\PapP2Test.php tests\Feature\PlanificationStrategiquePhaseP3Test.php tests\Feature\RecettesDurcissementR9Test.php tests\Feature\ReferentielAdministratifPhaseA7Test.php` ;
- `php artisan test tests\Feature\WorkflowP0Test.php tests\Feature\WorkflowP2Test.php` ;
- `php artisan test tests\Feature\WorkflowPapTest.php tests\Feature\WorkflowRecettesTest.php` ;
- `php artisan test tests\Feature\EncaissementsR3Test.php`.

## Resultats

- Tests unitaires SafeHtml : 2 tests, 6 assertions, succes.
- Tests budget cibles : 9 tests, 22 assertions, succes.
- Tests API/interoperabilite cibles : 16 tests, 47 assertions, succes.
- Routes Laravel : 654 routes chargees avec succes.
- Vues Blade : compilation cachee avec succes.
- Build Vite : succes apres restauration des dependances Node.
- Audit npm : 2 vulnerabilites critiques confirmees sur `shell-quote`, dependance transitive de `concurrently`, sans correctif disponible annonce par npm.
- Reproduction des 7 echecs signales : corrigee, 30 tests cibles passent.
- Regression workflow P0/P2 : 17 tests, 37 assertions, succes.
- Regression workflow PAP/recettes : 10 tests, 22 assertions, succes.
- Regression encaissements R3 : 9 tests, 19 assertions, succes.

## Recommandations restantes

- Ajouter une dependance HTML purifier robuste si les administrateurs doivent gerer des contenus HTML riches au-dela de la liste blanche actuelle.
- Etendre progressivement la pagination aux endpoints et services de reporting qui chargent de grands volumes avant export.
- Ajouter des jobs queues pour les exports volumineux PDF/Excel et les traitements GED/OCR si les donnees de production augmentent.
- Ajouter des tests d'autorisation sur les exports sensibles par role et perimetre institutionnel.
- Auditer les migrations futures datees apres l'exercice courant afin d'eviter une confusion entre planning projet et ordre de migration.
- Normaliser l'encodage des fichiers existants en UTF-8, plusieurs libelles historiques apparaissant avec des caracteres accentues corrompus.
- Remplacer `concurrently` dans la chaine de developpement par une alternative maintenue ou un script local controle, car la faille critique vient de `shell-quote` et npm indique qu'aucun correctif direct n'est disponible.
