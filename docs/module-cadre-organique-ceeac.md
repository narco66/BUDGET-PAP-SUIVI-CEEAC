# Module Cadre organique CEEAC

## Objectif

Le module integre le cadre organique de la Commission de la CEEAC dans le referentiel `structures` sans supprimer les donnees existantes. Les modules PAP, Budget, depense, reporting, acces et API continuent d'utiliser `structure_id`; le departement, la direction, le service et le responsable sont derives de l'arbre institutionnel.

## Composants

- Migration: `2026_06_20_000001_integrer_cadre_organique_ceeac.php`
- Synchronisation: `App\Services\Institution\CadreOrganiqueCeeacService`
- Indicateurs: `App\Services\Institution\InstitutionMetricsService`
- Commande audit: `php artisan institution:audit-cadre`
- Correction automatique: `php artisan institution:audit-cadre --fix`
- Web: `/organigramme`, `/institution`
- API: `/api/v1/structures`, `/api/v1/institution`

## Strategie de donnees

- Les structures sont synchronisees par `code`.
- Les libelles obsoletes connus sont renommes en place.
- Si une ancienne `Direction des Affaires Juridiques` existe, elle est reutilisee comme `BCJ` afin de conserver son `id`.
- Si un ancien `Secretariat Administratif` existe, il est reutilise comme `SG`.
- Les alias historiques `DEPT-PLAN` (rattache au Secretariat General), `DIR-BUDGET` et `SVC-EXEC` restent actifs pour compatibilite avec les donnees de demonstration.
- Phase A3 : chaque alias porte un lien `structure_officielle_id` vers le cadre officiel (`DSG`, `DIR-PPB`, `SVC-BUDGET`).

## Cadre officiel integre

Departements: `DPRES`, `DVP`, `DAPPS`, `DMCAEMF`, `DERNADR`, `DATI`, `DPGDHS`, `DSG`.

Le Secretariat General porte les directions suivantes:

- `DIR-CRPP`
- `DIR-CMR`
- `DIR-PPB`
- `DIR-RHMG`
- `DIR-SI`

Les departements metiers portent chacun au moins deux directions et deux services (phase A1) :

- `DAPPS` : `DIR-APPS-GOUV`, `DIR-APPS-PAIX`
- `DMCAEMF` : `DIR-MC`, `DIR-AEMF`
- `DERNADR` : `DIR-ENV`, `DIR-AGRI`
- `DATI` : `DIR-AT`, `DIR-INFRA`
- `DPGDHS` : `DIR-GDH`, `DIR-SOC`

Le Bureau du Conseiller Juridique est integre sous le Cabinet du President et conserve ses deux services:

- `SVC-CADS`
- `SVC-ARC`

## Controles d'integrite

La commande d'audit verifie:

- services sans direction;
- directions sans departement ancetre;
- departements metiers sans direction;
- hierarchies invalides (regles StructureHierarchieService);
- alias de demonstration non rapproches (phase A3);
- gouvernance institutionnelle (delegations, interims, affectations — phase A6);
- doublons actifs par type/libelle;
- utilisateurs rattaches a une structure inexistante;
- responsables invalides.

Avec `--fix`, elle resynchronise le cadre, rattache les structures orphelines reparables et nettoie les references utilisateur/responsable invalides.

## Perimetre structure (phase A4)

Le service `AccessScopeService` filtre les donnees metier par `structure_id` (scopes utilisateur + descendants + alias) :

- expressions de besoin (`ExpressionBesoinPolicy`, liste et formulaires) ;
- chaine RBM/PAP (`produits`, `activites` dans l'arbre et mutations) ;
- documents GED (`GedDocumentService::scopeAccessible`, `GedAccessService`).

## Exports institutionnels (phase A5)

Routes serveur (permission `structure.exporter`) :

- `GET /structures/export/annuaire.xlsx` — `structure.exporter.annuaire.excel`
- `GET /structures/export/annuaire.pdf` — `structure.exporter.annuaire.pdf`

Service : `InstitutionExportService` (lineage departement / direction / service / responsable, filtre perimetre A4).

## Gouvernance institutionnelle (phase A6)

Tables :

- `delegations` — delegation de pouvoir entre utilisateurs sur une structure ;
- `interims` — remplacement temporaire d'un titulaire ;
- `historiques_affectations` — trace des changements de structure utilisateur.

Service : `InstitutionGouvernanceService` (creation, cloture, controle actif, anomalies audit).

L'historique est alimente automatiquement via `UserService` lors de la creation ou du changement de `structure_id`.

## Workflows institutionnels (phase A7)

Le moteur `WorkflowService` accepte des validateurs institutionnels lorsque l'objet implemente `ConcernantStructureWorkflow` :

- responsable de la structure cible (service / direction / departement selon le role de l'etape) ;
- delegataire actif (`delegations`) ;
- interimaire actif (`interims`) ;
- ou detenteur du role Spatie requis (comportement historique conserve).

Service : `WorkflowInstitutionResolver` — resolution de la structure validateur depuis la hierarchie ou les codes institutionnels (`SG`, `PRES`).

Objets concernes : `ExpressionBesoin`, `Engagement` (structure via expression de besoin).

## Postes et permissions administratif (phase A8)

CRUD postes : routes `postes.*`, menu « Postes », policy alignee sur `structure.*` / `administratif.*`.

Permissions alias `administratif.*` (module dedie) miroir de `structure.*` via `RoleSeeder` ; helper `ReferentielAdministratifAuthorization`.

Import Excel structures :

- `GET /structures/import/modele.xlsx` — `structure.importer.modele`
- `POST /structures/import` — `structure.importer.structures`

Service : `InstitutionImportService` (validation hierarchique A1, upsert par `code`).

## API et consolidation tests (phase A9)

Endpoints REST (`auth:sanctum`, scope `lecture`) :

- `GET /api/v1/structures` — lineage departement / direction / service / responsable, filtre perimetre A4
- `GET /api/v1/institution` — indicateurs institutionnels filtres par perimetre
- `GET /api/v1/institution/gouvernance` — delegations et interims actifs, structures autorisees
- `GET /api/v1/me` — structure principale et perimetre utilisateur

Resources : `StructureResource`, `GouvernanceResource`.

Tests : `ReferentielAdministratifPhaseA9Test` (API, exports, delegations, perimetres).
