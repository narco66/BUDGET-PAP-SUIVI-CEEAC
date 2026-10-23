# Module 2 — Référentiel institutionnel RBM/GAR

Documentation technique du module complémentaire intégré à **TB-PAP-CEEAC** (GEST-BUDGET-PAP).

## Objectif

Centraliser la chaîne RBM/GAR institutionnelle :

**Pilier → Axe stratégique → Produit → Sous-produit → Activité → Tâche → Indicateur → Sources de vérification → Types / Unités → Cadre logique → Chaîne de résultats**

Ce module **étend l'existant** (D2) sans le remplacer : le registre `App\Rbm\Niveaux` et `RbmController` générique restent le cœur du CRUD hiérarchique.

## Tables

### Existantes (conservées)

| Table | Rôle |
|-------|------|
| `piliers` … `taches` | Chaîne RBM par exercice |
| `indicateurs` | IOV polymorphiques |
| `mesures` | Saisies périodiques |

### Ajoutées (migration `2026_06_17_000001_extend_rbm_referentiel_module.php`)

| Table | Rôle |
|-------|------|
| `types_indicateurs` | Catalogue des types d'indicateurs |
| `unites_mesure` | Catalogue des unités |
| `sources_verification` | Catalogue des sources documentaires |
| `cadres_logiques` | Cadre logique persisté par exercice (JSON) |
| `chaines_resultats` | Chaîne de résultats persistée par exercice (JSON) |
| `rbm_historiques` | Historique des actions archive/restore |
| `indicateur_source_verification` | Pivot indicateur ↔ source |

### Colonnes ajoutées (incrémental)

Sur `piliers`, `axes`, `produits`, `sous_produits`, `activites` : `statut`, `created_by`, `updated_by`.

Sur `taches` : `statut_referentiel`, `created_by`, `updated_by` (le champ `statut` existant reste le workflow tâche).

Sur `indicateurs` : `type_indicateur_id`, `unite_mesure_id`, `statut`, `created_by`, `updated_by`.

## Architecture

```
routes/web.php (prefix rbm.*)
    ├── RbmModuleController     → Hub, matrice, cadre logique, chaîne, PDF
    ├── RbmCatalogController    → CRUD catalogues référentiels
    ├── RbmExportController     → Exports Excel
    ├── RbmController           → CRUD générique (Niveaux)
    └── IndicateurController    → IOV par nœud

App\Services\Rbm\RbmReferentielService
    ├── matriceRbm(), cadreLogique(), chaineResultats()
    ├── archiver() / restaurer() / historiser()
    ├── raisonsBlocage()        → PAP, budget, dépense
    └── libelleDoublon()

App\Models\Concerns\HasRbmReferentiel → statut, traçabilité, historiques
App\Policies\RbmNodePolicy         → Pilier … Tache
```

## Routes principales

| Route | Description |
|-------|-------------|
| `GET /rbm` | Redirection hub (exercice ouvert) |
| `GET /rbm/hub` | Tableau de bord module |
| `GET /rbm/arbre/{exercice}` | Arbre hiérarchique |
| `GET /rbm/matrice/{exercice}` | Matrice aplatie |
| `GET /rbm/cadre-logique/{exercice}` | Cadre logique |
| `GET /rbm/chaine-resultats/{exercice}` | Chaîne de résultats |
| `GET /rbm/synthese/{exercice}/pdf` | Synthèse PDF |
| `GET /rbm/export/matrice/{exercice}` | Export Excel matrice |
| `GET /rbm/catalogues/{catalogue}` | Liste catalogue |
| `POST /rbm/{niveau}/{id}/archiver` | Archivage référentiel |
| `POST /rbm/{niveau}/{id}/restaurer` | Restauration |

Catalogues : `types-indicateurs`, `unites-mesure`, `sources-verification`.

## Permissions

Convention existante conservée (`rbm.voir`, `rbm.creer`, …). Permissions complémentaires :

- `rbm.archiver`
- `rbm.restaurer`
- `rbm.admin`

Seeder : `RbmPermissionsSeeder` (non destructif).

## Seeders

| Seeder | Rôle |
|--------|------|
| `RbmReferentielSeeder` | Types, unités, sources par défaut |
| `RbmPermissionsSeeder` | Permissions archiver/restaurer/admin |

Intégrés dans `SafeDatabaseSeeder` sans écraser les données existantes.

## Commandes de déploiement

```bash
php artisan migrate
php artisan db:seed --class=RbmPermissionsSeeder
php artisan db:seed --class=RbmReferentielSeeder
php artisan test --filter=Rbm
```

**Interdit** : `migrate:fresh`, `migrate:refresh`, `db:wipe`.

## Tests

| Fichier | Couverture |
|---------|------------|
| `tests/Feature/RbmChaineTest.php` | CRUD chaîne, RBAC, indicateurs |
| `tests/Feature/RbmReferentielModuleTest.php` | Hub, matrice, catalogues, cadre/chaîne |

## Non-régression

- Routes `rbm.store`, `rbm.arbre`, etc. inchangées (préfixe `rbm.*`).
- Données PAP 2026 (`Pap2026Seeder`) compatibles.
- Enum `indicateurs.type` conservé ; FK `type_indicateur_id` optionnelle.
- Aucun fichier existant supprimé.

## Fichiers créés / modifiés

Voir rapport d'implémentation dans la conversation ou exécuter :

```bash
git status
```

## Phase 2

### Import Excel
- `RbmChaineImport`, `RbmImportController`, vue `rbm/import.blade.php`
- Routes : `rbm.import.form`, `rbm.import.store`, `rbm.import.modele`

### Mesures IOV
- `MesureService`, `MesureController`, `MesurePolicy`
- Workflow : saisie → vérifiée → validée (feu vert/orange/rouge auto)
- Routes : `rbm.mesures.*`

### Permissions granulaires
- `{pilier|axe|produit|sous_produit|activite|tache|cadre_logique}.{voir|gerer}`
- `RbmAuthorization` + `RbmGranularPermissionsSeeder`

### Factories & tests
- `ExerciceFactory`, `PilierFactory`, `AxeFactory`, `IndicateurFactory`, `TypeIndicateurFactory`
- `tests/Feature/RbmPhase2Test.php`

## Phase 3

### Import indicateurs Excel
- `IndicateursImport`, `ModeleIndicateursExport`
- Route : `rbm.import.indicateurs`, `rbm.import.modele-indicateurs`

### Exports PDF catalogues
- `RbmPdfController` — matrice, catalogue par niveau, indicateurs
- Routes : `rbm.pdf.matrice`, `rbm.pdf.catalogue`, `rbm.pdf.indicateurs`

### Historique des modifications
- `RbmHistoriqueController` + traçabilité auto (create/update/delete/archive)
- Routes : `rbm.historique.index`, `rbm.historique.noeud`
- Bouton historique sur chaque nœud de l'arbre RBM

### Tests phase 3
- `tests/Feature/RbmPhase3Test.php`

## Phase 4

### Export Excel indicateurs
- `IndicateursCatalogueExport` + route `rbm.export.indicateurs`
- Lien depuis le hub RBM (carte Restitution)

### Édition indicateurs
- `IndicateurRequest`, routes `rbm.indicateurs.edit|update`
- Vue `indicateurs/edit.blade.php`, bouton modifier sur la liste IOV

### Notifications mesures IOV
- Migration `notifications`, `MesureVerifieeNotification`, `MesureValideeNotification`
- Déclenchées par `MesureService::verifier()` et `valider()`
- `NotificationController`, cloche dans la barre supérieure

### Sélecteur d'exercice (arbre)
- `$exercices` passé à la vue arbre, select dans `_nav-module`

### Tests phase 4
- `tests/Feature/RbmPhase4Test.php`
