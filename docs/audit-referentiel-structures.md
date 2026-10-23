# Audit d'alignement sur le referentiel Structures

## Perimetre

Le module `Structures` est confirme comme referentiel maitre organisationnel pour:

- Presidence, Vice-presidence et Secretariat general;
- Departements;
- Directions;
- Services;
- Cellules;
- Responsables rattaches aux structures.

L'audit executable est integre a la commande:

```bash
php artisan data:integrity-check --module=organisation
```

La correction non destructive des alias est disponible via:

```bash
php artisan institution:align-structures
php artisan institution:align-structures --apply
```

## Cartographie complete des references organisationnelles

Etat audite apres corrections:

- Tables auditees: 208
- Table maitre: `structures`
- Structures: 78 actives sur 78
- Repartition officielle:
  - presidence: 1
  - vice_presidence: 1
  - secretariat_general: 1
  - departement: 9
  - direction: 19
  - service: 38
  - cellule: 9
- Tables metier rattachees a `structures`: 32
- Colonnes textuelles departement/direction/service detectees: 0
- `structure_id` orphelins: 0
- Rattachements vers alias: 0 apres correction
- Structures inactives referencees: 0

Tables metier avec `structure_id`:

- `activites`
- `alertes`
- `besoin_consolidations`
- `budget_arbitrages`
- `budget_preparations`
- `budgets`
- `contrats`
- `controles_internes`
- `delegations`
- `expressions_besoin`
- `formations`
- `ged_acces`
- `ged_documents`
- `ged_dossiers`
- `ged_partages`
- `historiques_affectations`
- `immobilisations`
- `incidents`
- `interims`
- `lignes_budgetaires`
- `marches`
- `missions_audit`
- `missions_suivi`
- `non_conformites`
- `paps`
- `plans_amelioration`
- `produits`
- `programmes`
- `recettes`
- `recommandations`
- `risques`
- `users`

## Elements conformes

- Les principaux modules PAP, RBM/GAR, budget, recettes, GED, suivi, audit, risques, immobilisations, marches, contrats et utilisateurs sont rattaches au referentiel `structures`.
- Les formulaires majeurs consultes utilisent des selecteurs `structure_id` avec validation `exists:structures,id`.
- Les exports budgetaires et institutionnels deja existants lisent les libelles depuis la relation `structure`.
- La chaine de depense conserve l'heritage organisationnel depuis le besoin et l'engagement racine.
- Les workflows peuvent maintenant resoudre la structure des liquidations, ordonnancements et paiements via `structureIdWorkflow()`.

## Elements non conformes corriges

- 66 rattachements vers des structures alias ont ete remplaces par leurs structures officielles:
  - `activites`: 9 lignes
  - `budget_preparations`: 28 lignes
  - `lignes_budgetaires`: 28 lignes
  - `users`: 1 ligne
- La table `alertes` n'avait pas de rattachement organisationnel direct. Une migration ajoute `alertes.structure_id`, avec FK vers `structures`.
- Le registre d'alertes deduit maintenant `structure_id` depuis l'objet metier ou l'utilisateur assigne.
- L'ecran et l'export CSV des alertes affichent les codes et libelles officiels de structure.

## References dupliquees

- Des structures alias existent encore dans `structures` via `structure_officielle_id`: 3 alias.
- Elles ne sont plus utilisees par les tables metier apres correction.
- Elles peuvent rester pour compatibilite historique ou demonstration, mais ne doivent plus etre selectionnees dans les nouveaux formulaires.

## References orphelines

- Aucun `structure_id` orphelin detecte apres correction.

## Formulaires a surveiller

- Les formulaires avec `structure_id` doivent continuer a utiliser uniquement des listes deroulantes alimentees par `Structure::actif()`.
- Les prochaines evolutions doivent eviter tout champ libre `departement`, `direction`, `service` ou `unite_organisationnelle`.
- Les alertes n'ont pas de formulaire de creation manuelle centralise; leur rattachement est deduit automatiquement par `AlerteRegistryService`.

## Rapports, etats et exports

- Les exports d'alertes incluent maintenant `structure_code` et `structure`.
- Les rapports et exports existants doivent continuer a deriver departement/direction/service via la relation `structure` et les methodes `departement()`, `direction()`, `service()` du modele `Structure`.
- Aucune colonne textuelle organisationnelle non normalisee n'a ete detectee dans le schema audite.

## Workflows

- `Engagement` etait deja rattachable via `structureIdWorkflow()` depuis l'expression de besoin.
- `Liquidation`, `Ordonnancement` et `Paiement` exposent maintenant le meme rattachement via le trait commun `ConcerneDepense`.
- Les validations aval peuvent donc remonter automatiquement Service > Direction > Departement sans dupliquer `structure_id`.

## API

- L'API expose deja les structures officielles via `api.structures`.
- Les endpoints metier doivent continuer a exposer les identifiants `structure_id` et les libelles derives du referentiel.
- Les alertes API peuvent maintenant s'appuyer sur `alertes.structure_id`.

## Relations et integrite

- Nouvelle relation: `alertes.structure_id -> structures.id`.
- Les rattachements alias sont remediables par commande idempotente.
- L'audit `organisation` est ajoute au controle global planifie via `config/audit.php`.

## Plan de remediation priorise

- P1: maintenir `php artisan data:integrity-check --module=organisation` dans la supervision d'integrite.
- P1: executer `php artisan institution:align-structures --apply` apres tout import massif susceptible de creer des alias.
- P2: masquer les structures alias dans les selecteurs de creation/metier, sauf ecrans d'administration du referentiel.
- P2: enrichir les exports strategiques avec la hierarchie complete: departement, direction, service, responsable.
- P3: rendre progressivement `structure_id` obligatoire sur les tables metier une fois les historiques confirmes.
