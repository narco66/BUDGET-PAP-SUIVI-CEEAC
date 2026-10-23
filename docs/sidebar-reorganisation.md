# Reorganisation du sidebar BUDGET-PAP-SUIVI-CEEAC

## 1. Cartographie du menu actuel

Avant reorganisation, le sidebar etait une liste plate de sections et de liens:

- Tableau de bord: lien unique vers l'accueil.
- Institution: structures, organigramme, programmes, fonctions, postes.
- Planification & PAP: planification strategique, RBM/GAR, PAP, cockpit PAP.
- Budgetisation: referentiel financier, nomenclature, budget, enveloppes, structure du budget, suivi financier.
- Recettes & ressources: recettes, encaissements, appels de fonds, etats des recettes.
- Chaine de la depense: cockpit, besoins, engagements, liquidations, ordonnancements, paiements, rapprochement.
- Marches & contrats: marches/PPM, contrats.
- Suivi & evaluation: suivi-evaluation, campagnes, recommandations, formations, immobilisations.
- Restitution & analyse: tableaux de bord, tresorerie, cockpit executif, reporting, rapports personnalises, exports.
- GED: tableau de bord GED, connaissances, documents, depot, recherche, classement, categories, types, dossiers, versions, signatures, circuits, partages, confidentiels, expires, archives, conservation, journal, parametres.
- Securite & acces: utilisateurs, roles, profils, journal, gouvernance, audit, risques, controle interne, conformite, supervision, files d'attente, incidents, sauvegardes.
- Systeme & workflow: notifications, alertes, workflow, validations, audit workflow, preferences, modeles e-mail, circuits workflow, interoperabilite API.

## 2. Constats d'audit

- Le menu etait fonctionnel mais trop plat: plusieurs domaines avaient plus de 10 liens visibles.
- Des fonctions de pilotage etaient dispersees entre `Tableau de bord`, `Restitution & Analyse`, `PAP` et `Administration technique`.
- La GED melangeait navigation metier, parametrage, archivage et workflow documentaire dans une seule section longue.
- La securite, l'audit, la conformite et l'administration technique etaient regroupes dans une section unique, alors qu'ils correspondent a des responsabilites distinctes.
- La logique processus etait partiellement respectee pour la depense, mais moins claire pour budget, recettes, PAP, suivi et reporting.
- Les permissions existaient deja et filtraient les liens, mais il manquait une structure de groupe permettant d'afficher seulement les blocs utiles par profil.

## 3. Cartographie cible implementee

- Pilotage
  - Accueil institutionnel
  - Tableaux de bord: executif, strategique, financier, operationnel, PAP, technique
- Referentiels
  - Institutionnel
  - RBM / GAR
  - Financier
- Planification et PAP
  - Planification strategique
  - Plan annuel de performance
- Budget et ressources
  - Gestion budgetaire
  - Gestion des recettes
- Execution
  - Chaine de la depense
  - Marches et contrats
- Suivi et analyse
  - Suivi et performance
  - Reporting et analyse
- Collaboration
  - Gestion documentaire
  - Gestion des connaissances
- Gouvernance SI
  - Workflow et validation
  - Notifications et alertes
  - Interoperabilite et API
  - Audit, controle et conformite
- Administration
  - Utilisateurs et acces
  - Administration technique

## 4. Menus deplaces

- `Cockpit executif`, `Tableaux de bord`, `Tresorerie unifiee`, `Cockpit PAP` et `Supervision technique` sont regroupes sous `Pilotage > Tableaux de bord`.
- Les liens institutionnels sont regroupes dans `Referentiels > Institutionnel`.
- Les liens budgetaires sont separes entre `Referentiels > Financier` et `Budget et ressources > Gestion budgetaire`.
- Les notifications, alertes et workflows quittent `Systeme & Workflow` pour `Gouvernance SI`.
- Audit, risques, controle interne et conformite quittent `Securite & Acces` pour `Gouvernance SI > Audit, controle et conformite`.
- Utilisateurs, roles, profils et journal de connexion sont recentres dans `Administration > Utilisateurs et acces`.
- GED connaissances devient `Collaboration > Gestion des connaissances`.

## 5. Menus fusionnes

- `Restitution & Analyse` est fusionne dans `Pilotage` et `Suivi et analyse`.
- `Securite & Acces` est scinde puis fusionne dans `Gouvernance SI` et `Administration`.
- `Systeme & Workflow` est fusionne dans `Gouvernance SI`.
- Les entrees de la GED restent conservees mais passent sous un groupe repliable unique.

## 6. Menus supprimes

Aucune fonctionnalite existante n'a ete supprimee. Les suppressions sont uniquement des suppressions de sections redondantes dans la navigation:

- `Budgetisation`
- `Restitution & Analyse`
- `Securite & Acces`
- `Systeme & Workflow`

Leurs liens ont ete conserves et repositionnes.

## 7. Menus crees

- Groupes repliables: `Tableaux de bord`, `Institutionnel`, `RBM / GAR`, `Financier`, `Planification strategique`, `Plan annuel de performance`, `Gestion budgetaire`, `Gestion des recettes`, `Chaine de la depense`, `Marches et contrats`, `Suivi et performance`, `Reporting et analyse`, `Gestion documentaire`, `Gestion des connaissances`, `Workflow et validation`, `Notifications et alertes`, `Interoperabilite et API`, `Audit, controle et conformite`, `Utilisateurs et acces`, `Administration technique`.
- Lien expose dans le sidebar: `Documentation API` vers la route existante `api.docs`.

## 8. Recommandations UX/UI

- Garder les sections de premier niveau limitees aux grands domaines institutionnels.
- Eviter les listes longues non groupees; les groupes repliables sont preferables au-dela de 5 liens.
- Ouvrir automatiquement le groupe qui contient la page active.
- Conserver des icones coherentes par domaine: pilotage, referentiels, budget, execution, suivi, collaboration, gouvernance, administration.
- Maintenir le filtrage par permission au niveau des groupes et des liens.
- Sur mobile, afficher le sidebar en bloc vertical scrollable au-dessus du contenu pour eviter les debordements lateraux.

## 9. Plan d'implementation

1. Ajouter le type `group` dans le service de navigation.
2. Filtrer les groupes et les enfants selon les permissions existantes.
3. Supprimer automatiquement les groupes et sections vides.
4. Mettre a jour le composant Blade pour rendre les groupes repliables.
5. Reorganiser `config/menu.php` selon la cartographie cible.
6. Valider la syntaxe PHP, la compilation Blade, les routes referencees et le build Vite.
7. Tester visuellement avec des profils representatifs: executif, DPPB, controle financier, agent comptable, gestionnaire et administrateur.
