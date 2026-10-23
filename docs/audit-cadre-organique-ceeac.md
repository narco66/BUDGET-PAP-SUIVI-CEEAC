# Audit prealable - Cadre organique CEEAC

Date: 2026-06-11

## Perimetre analyse

L'audit a couvert les migrations, seeders, modeles Eloquent, controleurs, vues Blade, routes web/API, services de reporting, services d'acces, tests et l'etat courant de la base MySQL `gest_budget_pap`.

## Etat existant

- Le referentiel institutionnel existe deja via la table auto-referencee `structures`.
- Les types disponibles sont: `presidence`, `vice_presidence`, `secretariat_general`, `commissariat`, `departement`, `direction`, `service`, `cellule`.
- Les relations metier utilisent principalement `structure_id`: utilisateurs, programmes, produits, activites, lignes budgetaires, expressions de besoin, marches, contrats, formations et recommandations.
- Les responsables sont deja supportes via `responsable_user_id` sur `structures`, `activites`, `taches` et `recommandations`.
- L'organigramme existe deja en lecture arborescente via `/organigramme`, mais sans export PDF/PNG/SVG, recherche instantanee, mode graphique ou impression A3.
- Les scopes d'acces utilisent la descendance de `structures`; une reorganisation par `parent_id` preserve donc naturellement les perimetres.

## Etat base observe

Avant integration du nouveau cadre, la base contient seulement une ossature de demonstration:

- `PRES` - Presidence de la Commission
- `VPRES` - Vice-Presidence de la Commission
- `SG` - Secretariat General
- `DEPT-PLAN` - Departement de la Planification et du Budget
- `DIR-BUDGET` - Direction du Budget
- `SVC-EXEC` - Service de l'Execution Budgetaire

Les libelles affiches en console presentent des caracteres alteres par l'encodage du terminal, mais les objets fonctionnels sont presents.

## Ecarts fonctionnels

- Les 8 entites departementales attendues ne sont pas toutes presentes: `DPRES`, `DVP`, `DAPPS`, `DMCAEMF`, `DERNADR`, `DATI`, `DPGDHS`, `DSG`.
- La transformation "Secretariat Administratif" vers "Secretariat General" n'est pas garantie par une migration de donnees dediee.
- Le remplacement "Direction des Affaires Juridiques" vers "Bureau du Conseiller Juridique" n'est pas garanti par une migration de donnees dediee.
- Les services juridiques attendus doivent etre preserves et rattaches au Bureau du Conseiller Juridique.
- Les cabinets du President et du Vice-President ne sont pas seedes.
- Les directions rattachees au Secretariat General ne sont pas seedes selon le nouveau cadre.
- Les controles d'integrite institutionnels ne sont pas automatises sous forme de commande audit/correction.
- Les tableaux de bord institutionnels par departement/direction/service ne sont pas centralises dans un service dedie.
- L'API REST ne publie pas encore le referentiel institutionnel ni les indicateurs institutionnels.

## Ecarts techniques

- Le modele `Structure` ne fournit pas encore d'accesseurs normalises pour retrouver le departement, la direction et le service d'une structure quelconque.
- Les modules metier utilisent `structure_id`; cela preserve les donnees, mais les libelles "Departement / Direction / Service / Responsable" doivent etre derives de la hierarchie pour eviter des colonnes redondantes.
- Le seeder actuel est minimal et oriente demonstration.
- Les workflows, validations et notifications s'appuient sur les roles et structures existants, sans nomenclature organique officielle complete.

## Contraintes de preservation

- Aucune suppression physique ne doit etre effectuee.
- Les codes existants doivent etre conserves autant que possible.
- Les libelles obsoletes doivent etre renommes en place afin de conserver les cles primaires et les relations existantes.
- Les anciennes structures de demonstration peuvent rester actives si elles portent des donnees, mais elles doivent etre rattachees a un parent officiel coherent.
- Les rattachements metier doivent continuer a passer par `structure_id`; la lecture departement/direction/service doit etre derivee de l'arbre.

## Strategie retenue

- Ajouter un service de synchronisation du cadre organique officiel, transactionnel et idempotent.
- Ajouter une migration qui execute cette synchronisation et corrige les libelles obsoletes sans supprimer de donnees.
- Mettre a jour le seeder `StructureSeeder` pour rejouer le cadre officiel sur les nouveaux environnements.
- Ajouter un service de ligne hierarchique permettant aux modules PAP, Budget, depense, reporting et API de consommer une lecture coherente `departement -> direction -> service -> responsable`.
- Enrichir l'organigramme existant avec recherche, vues arbre/graphique, exports SVG/PNG/PDF cote navigateur et impression A3.
- Ajouter un tableau de bord institutionnel dedie et une commande d'audit/correction des anomalies.
