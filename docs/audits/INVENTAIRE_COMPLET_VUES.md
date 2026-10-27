# Inventaire complet des vues — BUDGET-CEEAC / GESBUDEP

**Date :** 26 octobre 2026 · **Source :** routes GET web de l'application (`database/scripts/inventaire_vues.php`, données brutes : `docs/audits/vues/inventaire_routes.json`) croisées avec les audits Playwright (profil gestionnaire budgétaire, 374 vues) et l'audit des vues d'administration (rendu serveur administrateur, 87 vues).

**Volume :** 438 routes GET web : 358 vues, 58 fichiers (exports, PDF), 13 réponses JSON, 7 redirections ; 312 gabarits Blade distincts. Layout commun : `layouts.app` (sauf authentification et documents imprimables). Composant principal = vue Blade (l'interface n'est pas en React).

Légende de l'état : **OK** = page chargée et auditée ; WCAG = nœuds en violation (axe-core) ; débordement = défilement horizontal par format ; « non auditée » = paramètre sans exemple accessible ou sortie non HTML.


## Module `accueil`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/` | — | Closure@__invoke | closure | — | — | non auditée (closure) |

## Module `admin`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/admin/interoperabilite` | admin.interoperabilite.index | InteropHubController@index | `admin.interoperabilite.index` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/apps` | admin.interoperabilite.apps.index | InteropAppController@index | `admin.interoperabilite.apps.index` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/apps/create` | admin.interoperabilite.apps.create | InteropAppController@create | `admin.interoperabilite.apps.form` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/apps/{app}` | admin.interoperabilite.apps.show | InteropAppController@show | `admin.interoperabilite.apps.show` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/apps/{app}/edit` | admin.interoperabilite.apps.edit | InteropAppController@edit | `admin.interoperabilite.apps.form` | api.admin | — | — |
| `/admin/interoperabilite/exports/inventaire/{format}` | admin.interoperabilite.exports.inventaire | InteropExportController@inventaire | fichier | api.publish | — | non auditée (fichier) |
| `/admin/interoperabilite/exports/securite/{format}` | admin.interoperabilite.exports.securite | InteropExportController@rapportSecurite | `admin.interoperabilite.exports.rapport-securite-pdf` | api.monitor | — | — |
| `/admin/interoperabilite/journal` | admin.interoperabilite.journal | InteropJournalController@index | `admin.interoperabilite.journal.index` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/monitoring` | admin.interoperabilite.monitoring | InteropMonitoringController@index | `admin.interoperabilite.monitoring.index` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/webhooks` | admin.interoperabilite.webhooks.index | InteropWebhookController@index | `admin.interoperabilite.webhooks.index` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/webhooks/create` | admin.interoperabilite.webhooks.create | InteropWebhookController@create | `admin.interoperabilite.webhooks.form` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/webhooks/{webhook}` | admin.interoperabilite.webhooks.show | InteropWebhookController@show | `admin.interoperabilite.webhooks.show` | api.admin | — | WCAG 0 |
| `/admin/interoperabilite/webhooks/{webhook}/edit` | admin.interoperabilite.webhooks.edit | InteropWebhookController@edit | `admin.interoperabilite.webhooks.form` | api.admin | — | — |
| `/admin/notifications/templates` | admin.notifications.templates.index | AdminEmailTemplateController@index | `admin.notifications.templates.index` | notifications.admin | — | WCAG 0 |
| `/admin/notifications/templates/{template}/edit` | admin.notifications.templates.edit | AdminEmailTemplateController@edit | `admin.notifications.templates.form` | notifications.admin | — | — |
| `/admin/workflows` | admin.workflows.index | WorkflowAdminController@index | `workflow.admin.index` | workflow.admin | — | WCAG 0 |
| `/admin/workflows/create` | admin.workflows.create | WorkflowAdminController@create | `workflow.admin.form` | workflow.create | — | WCAG 0 |
| `/admin/workflows/{workflow}/edit` | admin.workflows.edit | WorkflowAdminController@edit | `workflow.admin.form` | workflow.update | — | — |

## Module `administration-technique`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/administration-technique` | administration-technique.cockpit | AdministrationTechniqueController@cockpit | `administration-technique.cockpit` | admin_technique.monitor | — | WCAG 0 |
| `/administration-technique/files-attente` | administration-technique.queues.index | QueueMonitorController@index | `administration-technique.queues.index` | — | — | WCAG 3 |
| `/administration-technique/files-attente/echoues/{uuid}` | administration-technique.queues.show | QueueMonitorController@show | `administration-technique.queues.show` | — | — | — |

## Module `alertes`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/alertes` | alertes.index | AlerteController@index | `alertes.index` | alertes.view | — | WCAG 0 |
| `/alertes/export/csv` | alertes.export | AlerteController@export | vue | alertes.manage | — | — |

## Module `appels-fonds`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/appels-fonds` | appels-fonds.index | AppelFondsController@index | `appels-fonds.index` | viewAny | — | WCAG 0 |
| `/appels-fonds/create` | appels-fonds.create | AppelFondsController@create | `appels-fonds.create` | create | — | WCAG 1 |
| `/appels-fonds/{appelsFond}` | appels-fonds.show | AppelFondsController@show | `appels-fonds.show` | view | — | WCAG 1 |

## Module `audit`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/audit` | audit.index | AuditController@index | `audit.index` | — | — | WCAG 1 |
| `/audit/dossiers/{type}/{id}` | audit.dossier | AuditController@dossier | `audit.dossier` | — | — | — |
| `/audit/export/{format}` | audit.export | AuditController@export | json | — | — | non auditée (json) |
| `/audit/integrite` | audit.integrite | AuditController@integrite | `audit.integrite` | audit.integrity.execute | — | WCAG 0 |
| `/audit/securite` | audit.securite | AuditController@securite | `audit.securite` | — | — | WCAG 0 |
| `/audit/tableau-de-bord` | audit.tableau-de-bord | AuditController@tableauDeBord | `audit.tableau-de-bord` | — | WCAG 3 | WCAG 1 |
| `/audit/utilisateurs/{user}` | audit.utilisateur | AuditController@utilisateur | `audit.utilisateur` | — | — | — |
| `/audit/{journal}` | audit.show | AuditController@show | `audit.show` | — | — | WCAG 0 |

## Module `audit-missions`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/audit-missions` | audit-missions.index | MissionAuditController@index | `audit-missions.index` | audit.voir | WCAG 2 | WCAG 0 |
| `/audit-missions/create` | audit-missions.create | MissionAuditController@create | `audit-missions.form` | audit.creer | — | WCAG 0 |
| `/audit-missions/{mission}` | audit-missions.show | MissionAuditController@show | `audit-missions.show` | audit.voir | — | WCAG 0 |
| `/audit-missions/{mission}/edit` | audit-missions.edit | MissionAuditController@edit | `audit-missions.form` | audit.modifier | — | — |

## Module `besoins`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/besoins` | besoins.index | ExpressionBesoinController@index | `besoins.index` | viewAny | WCAG 43 | WCAG 0 |
| `/besoins/consolidation` | besoins.consolidation | BesoinConsolidationController@index | `besoins.consolidation` | — | — | WCAG 0 |
| `/besoins/create` | besoins.create | ExpressionBesoinController@create | `besoins.create` | create | — | WCAG 0 |
| `/besoins/{besoin}` | besoins.show | ExpressionBesoinController@show | `besoins.show` | view | — | WCAG 0 |
| `/besoins/{besoin}/edit` | besoins.edit | ExpressionBesoinController@edit | `besoins.edit` | update | — | — |
| `/besoins/{besoin}/pdf` | besoins.pdf | ExpressionBesoinController@pdf | fichier | view | — | non auditée (fichier) |

## Module `budget`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/budget` | budget.index | LigneBudgetaireController@index | `budget.index` | viewAny | WCAG 34 · débord. tablette 285px, mobile 403px | WCAG 1 |
| `/budget/create` | budget.create | LigneBudgetaireController@create | `budget.create` | create | WCAG 7 | WCAG 0 |
| `/budget/enveloppe` | budget.enveloppe.index | BudgetEnveloppeController@index | `budget.enveloppe.index` | viewAny | WCAG 1 | WCAG 0 |
| `/budget/enveloppe/create` | budget.enveloppe.create | BudgetEnveloppeController@create | `budget.enveloppe.create` | create | WCAG 1 | WCAG 0 |
| `/budget/enveloppe/{budget}` | budget.enveloppe.show | BudgetEnveloppeController@show | `budget.enveloppe.show` | view, agir | WCAG 1 | WCAG 0 |
| `/budget/enveloppe/{budget}/edit` | budget.enveloppe.edit | BudgetEnveloppeController@edit | `budget.enveloppe.edit` | update | — | — |
| `/budget/enveloppe/{budget}/preparations` | budget.enveloppe.preparations | BudgetPreparationController@index | `budget.enveloppe.preparations` | preparer | — | — |
| `/budget/enveloppe/{budget}/rapport` | budget.enveloppe.rapport | BudgetRapportController@show | `budget.enveloppe.rapport` | view | — | — |
| `/budget/enveloppe/{budget}/revisions` | budget.enveloppe.revisions | BudgetRevisionController@index | `budget.enveloppe.revisions` | rectifier | — | — |
| `/budget/enveloppe/{budget}/simulations` | budget.enveloppe.simulations | BudgetSimulationController@index | `budget.enveloppe.simulations` | simuler | — | — |
| `/budget/export` | budget.export | BudgetImportController@exporter | fichier | ligne_budgetaire.exporter | — | non auditée (fichier) |
| `/budget/import` | budget.import.form | BudgetImportController@form | `budget.import` | ligne_budgetaire.voir | WCAG 5 | WCAG 0 |
| `/budget/modele/lignes` | budget.modele.lignes | BudgetImportController@modeleLignes | fichier | — | — | non auditée (fichier) |
| `/budget/modele/nomenclature` | budget.modele.nomenclature | BudgetImportController@modeleNomenclature | fichier | — | — | non auditée (fichier) |
| `/budget/modele/pap` | budget.modele.pap | BudgetImportController@modelePap | fichier | — | — | non auditée (fichier) |
| `/budget/rapport` | budget.rapport | BudgetRapportController@exercice | `budget.enveloppe.rapport` | viewAny | WCAG 5 | WCAG 3 |
| `/budget/{ligne}/edit` | budget.edit | LigneBudgetaireController@edit | `budget.edit` | update | — | — |

## Module `conformite`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/conformite` | conformite.index | NonConformiteController@index | `conformite.index` | conformite.voir | WCAG 5 | WCAG 0 |
| `/conformite/create` | conformite.create | NonConformiteController@create | `conformite.form` | conformite.creer | — | WCAG 0 |
| `/conformite/{nonConformite}` | conformite.show | NonConformiteController@show | `conformite.show` | conformite.voir | — | WCAG 0 |
| `/conformite/{nonConformite}/edit` | conformite.edit | NonConformiteController@edit | `conformite.form` | conformite.modifier | — | — |

## Module `contrats`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/contrats` | contrats.index | ContratController@index | `contrats.index` | contrat.voir | WCAG 3 | WCAG 0 |
| `/contrats/create` | contrats.create | ContratController@create | `contrats.form` | contrat.creer | WCAG 1 | WCAG 0 |
| `/contrats/{contrat}` | contrats.show | ContratController@show | `contrats.show` | contrat.voir | WCAG 1 | WCAG 0 |
| `/contrats/{contrat}/edit` | contrats.edit | ContratController@edit | `contrats.form` | contrat.modifier | — | — |

## Module `controle-interne`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/controle-interne` | controle-interne.index | ControleInterneController@index | `controle-interne.index` | controle_interne.voir | WCAG 12 | WCAG 0 |
| `/controle-interne/create` | controle-interne.create | ControleInterneController@create | `controle-interne.form` | controle_interne.creer | — | WCAG 0 |
| `/controle-interne/{controle}/edit` | controle-interne.edit | ControleInterneController@edit | `controle-interne.form` | controle_interne.modifier | — | — |

## Module `conventions`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/conventions/{convention}` | conventions.show | DecaissementConventionController@show | `referentiel.conventions.show` | view | — | — |
| `/conventions/{convention}/edit` | conventions.edit | ConventionFinancementController@edit | `referentiel.conventions.edit` | update | — | — |

## Module `dashboard`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/dashboard` | dashboard | DashboardController@index | `dashboard` | — | WCAG 9 | WCAG 0 |

## Module `depense`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/depense/dossier/besoin/{besoin}` | depense.dossier.besoin | DepenseDossierController@besoin | `depense.dossier` | view | — | — |
| `/depense/dossier/engagement/{engagement}` | depense.dossier.engagement | DepenseDossierController@engagement | `depense.dossier` | view | — | — |
| `/depense/export/besoins` | depense.export.besoins | DepenseExportController@besoins | fichier | — | — | non auditée (fichier) |
| `/depense/export/consolide` | depense.export.consolide | DepenseExportController@journalConsolide | fichier | — | — | non auditée (fichier) |
| `/depense/export/ecritures` | depense.export.ecritures | DepenseExportController@ecrituresComptables | fichier | — | — | non auditée (fichier) |
| `/depense/export/engagements` | depense.export.engagements | DepenseExportController@journalEngagements | fichier | — | — | non auditée (fichier) |
| `/depense/export/liquidations` | depense.export.liquidations | DepenseExportController@journalLiquidations | fichier | — | — | non auditée (fichier) |
| `/depense/export/ordonnancements` | depense.export.ordonnancements | DepenseExportController@journalOrdonnancements | fichier | — | — | non auditée (fichier) |
| `/depense/export/paiements` | depense.export.paiements | DepenseExportController@journalPaiements | fichier | — | — | non auditée (fichier) |
| `/depense/export/restes-a-engager` | depense.export.restes-engager | DepenseExportController@restesAEngager | fichier | — | — | non auditée (fichier) |
| `/depense/export/restes-a-payer` | depense.export.restes | DepenseExportController@restesAPayer | fichier | — | — | non auditée (fichier) |
| `/depense/export/{cle}/archiver` | depense.export.archiver | DepenseExportController@archiver | fichier | depenses.export | — | non auditée (fichier) |
| `/depense/rapprochement` | depense.rapprochement.index | RapprochementBancaireController@index | `depense.rapprochement.index` | — | WCAG 1 | WCAG 0 |
| `/depense/rapprochement/create` | depense.rapprochement.create | RapprochementBancaireController@create | `depense.rapprochement.create` | — | WCAG 1 | WCAG 0 |
| `/depense/rapprochement/{rapprochement}` | depense.rapprochement.show | RapprochementBancaireController@show | `depense.rapprochement.show` | — | WCAG 1 | WCAG 0 |
| `/depense/verifier/{type}/{id}` | depense.verifier | DepenseDossierController@verifier | `depense.verifier` | view | — | — |
| `/depense/{type}` | depense.index | EtapeDepenseController@index | `depense.index` | viewAny | WCAG 23 | WCAG 0 |
| `/depense/{type}/create` | depense.create | EtapeDepenseController@create | `depense.form` | create | WCAG 1 | WCAG 0 |
| `/depense/{type}/{id}` | depense.show | EtapeDepenseController@show | `depense.show` | view, agir | WCAG 1 | WCAG 0 |
| `/depense/{type}/{id}/pdf` | depense.pdf | EtapeDepenseController@pdf | fichier | view | — | non auditée (fichier) |

## Module `depense-cockpit`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/depense-cockpit` | depense.cockpit | CockpitDepenseController@index | `depense.cockpit` | engagement.voir | WCAG 4 | WCAG 0 |

## Module `encaissements`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/encaissements` | encaissements.index | EncaissementController@index | `encaissements.index` | viewAny | WCAG 2 | WCAG 0 |
| `/encaissements/create` | encaissements.create | EncaissementController@create | `encaissements.create` | create | WCAG 2 | WCAG 1 |
| `/encaissements/{encaissement}` | encaissements.show | EncaissementController@show | `encaissements.show` | view, agir | WCAG 2 | WCAG 1 |

## Module `engagements`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/engagements` | engagements.index | EngagementController@index | `engagements.index` | viewAny | WCAG 22 | WCAG 0 |
| `/engagements/create` | engagements.create | EngagementController@create | `engagements.create` | create, depenses.admin | — | WCAG 0 |
| `/engagements/{engagement}` | engagements.show | EngagementController@show | `engagements.show` | view | — | WCAG 0 |
| `/engagements/{engagement}/certificat` | engagements.certificat | EngagementController@certificat | fichier | view | — | non auditée (fichier) |
| `/engagements/{engagement}/pdf` | engagements.pdf | EngagementController@pdf | fichier | view | — | non auditée (fichier) |

## Module `exercices`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/exercices` | exercices.index | ExerciceController@index | `exercices.index` | viewAny | WCAG 3 | WCAG 0 |
| `/exercices/create` | exercices.create | ExerciceController@create | `exercices.create` | create | — | WCAG 0 |
| `/exercices/{exercice}` | exercices.show | ExerciceController@show | `exercices.show` | view | — | WCAG 0 |
| `/exercices/{exercice}/edit` | exercices.edit | ExerciceController@edit | `exercices.edit` | update | — | — |

## Module `fonctions`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/fonctions` | fonctions.index | FonctionController@index | `fonctions.index` | viewAny | WCAG 1 | WCAG 0 |
| `/fonctions/create` | fonctions.create | FonctionController@create | `fonctions.create` | create | — | WCAG 0 |
| `/fonctions/{fonction}/edit` | fonctions.edit | FonctionController@edit | `fonctions.edit` | update | — | — |

## Module `formations`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/formations` | formations.index | FormationController@index | `formations.index` | formation.voir | WCAG 4 | WCAG 0 |
| `/formations/create` | formations.create | FormationController@create | `formations.form` | formation.creer | — | WCAG 0 |
| `/formations/{formation}` | formations.show | FormationController@show | `formations.show` | formation.voir | — | WCAG 0 |
| `/formations/{formation}/edit` | formations.edit | FormationController@edit | `formations.form` | formation.modifier | — | — |

## Module `ged`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/ged` | ged.dashboard | GedDashboardController@index | `ged.dashboard` | ged.voir | WCAG 4 | WCAG 0 |
| `/ged/archives` | ged.archives | GedDocumentController@archives | `ged.archives.index` | — | WCAG 4 | WCAG 0 |
| `/ged/categories` | ged.categories | GedAdminController@categories | `ged.admin.categories` | ged.admin | — | WCAG 0 |
| `/ged/classement` | ged.classement | GedAdminController@classement | `ged.classement.index` | ged.voir | WCAG 2 | WCAG 1 |
| `/ged/connaissances` | ged.connaissances.index | ConnaissanceController@index | `ged.connaissances.index` | ged.voir, ged.admin | WCAG 4 | WCAG 0 |
| `/ged/connaissances/create` | ged.connaissances.create | ConnaissanceController@create | `ged.connaissances.form` | ged.creer | — | WCAG 0 |
| `/ged/connaissances/{connaissance}` | ged.connaissances.show | ConnaissanceController@show | `ged.connaissances.show` | ged.voir, ged.admin | — | WCAG 0 |
| `/ged/connaissances/{connaissance}/edit` | ged.connaissances.edit | ConnaissanceController@edit | `ged.connaissances.form` | — | — | — |
| `/ged/conservation` | ged.conservation | GedAdminController@conservation | `ged.conservation.index` | ged.admin | — | WCAG 0 |
| `/ged/documents` | ged.documents.index | GedDocumentController@index | `ged.documents.index` | viewAny | WCAG 18 | WCAG 0 |
| `/ged/documents/confidentiels` | ged.documents.confidentiels | GedDocumentController@confidentiels | `ged.documents.confidentiels` | — | WCAG 1 | WCAG 0 |
| `/ged/documents/create` | ged.documents.create | GedDocumentController@create | `ged.documents.create` | create | — | WCAG 0 |
| `/ged/documents/expires` | ged.documents.expires | GedDocumentController@expires | `ged.documents.expires` | — | WCAG 1 | WCAG 0 |
| `/ged/documents/{document}` | ged.documents.show | GedDocumentController@show | `ged.documents.show` | view | WCAG 1 | WCAG 0 |
| `/ged/documents/{document}/download` | ged.documents.download | GedDocumentController@download | fichier | download | — | non auditée (fichier) |
| `/ged/documents/{document}/edit` | ged.documents.edit | GedDocumentController@edit | `ged.documents.edit` | update | — | — |
| `/ged/documents/{document}/preview` | ged.documents.preview | GedDocumentController@preview | fichier | preview | — | non auditée (fichier) |
| `/ged/dossiers` | ged.dossiers | GedAdminController@dossiers | `ged.dossiers.index` | ged.voir | WCAG 1 | WCAG 0 |
| `/ged/exports/archives/excel` | ged.exports.archives.excel | GedExportController@registreArchives | fichier | ged.exporter | — | non auditée (fichier) |
| `/ged/exports/documents/excel` | ged.exports.documents.excel | GedExportController@registreDocuments | fichier | ged.exporter | — | non auditée (fichier) |
| `/ged/exports/documents/{document}/bordereau-archivage` | ged.exports.documents.bordereau_archivage | GedExportController@bordereauArchivage | `ged.pdf.bordereau_archivage` | view | — | — |
| `/ged/exports/documents/{document}/bordereau-depot` | ged.exports.documents.bordereau_depot | GedExportController@bordereauDepot | `ged.pdf.bordereau_depot` | view | — | — |
| `/ged/exports/documents/{document}/certificat-integrite` | ged.exports.documents.certificat_integrite | GedExportController@certificatIntegrite | `ged.pdf.certificat_integrite` | view | — | — |
| `/ged/exports/documents/{document}/certificat-signature` | ged.exports.documents.certificat_signature | GedExportController@certificatSignature | `ged.pdf.certificat_signature` | view | — | — |
| `/ged/exports/documents/{document}/fiche` | ged.exports.documents.fiche | GedExportController@ficheDocument | `ged.pdf.fiche` | view | — | — |
| `/ged/exports/journal/excel` | ged.exports.journal.excel | GedExportController@registreJournal | fichier | ged.consulter_journal | — | non auditée (fichier) |
| `/ged/exports/registres/{registre}/archiver` | ged.exports.registres.archiver | GedExportController@archiver | fichier | — | — | non auditée (fichier) |
| `/ged/exports/signatures/excel` | ged.exports.signatures.excel | GedExportController@registreSignatures | fichier | ged.exporter | — | non auditée (fichier) |
| `/ged/journal` | ged.journal | GedAdminController@journal | `ged.journal.index` | ged.consulter_journal | — | WCAG 0 |
| `/ged/parametres` | ged.parametres | GedAdminController@parametres | `ged.parametres.index` | ged.admin | — | WCAG 0 |
| `/ged/partages` | ged.partages | GedDocumentController@partages | `ged.partages.index` | ged.voir | WCAG 1 | WCAG 0 |
| `/ged/recherche` | ged.recherche | GedDocumentController@rechercher | `ged.recherche` | ged.voir | WCAG 26 | WCAG 0 |
| `/ged/signatures` | ged.signatures.index | GedAdminController@signatures | `ged.signatures.index` | ged.voir | WCAG 1 | WCAG 0 |
| `/ged/types` | ged.types | GedAdminController@types | `ged.admin.types` | ged.admin | — | WCAG 0 |
| `/ged/validations` | ged.validations | GedAdminController@validations | `ged.validations.index` | ged.valider | — | WCAG 0 |
| `/ged/versions` | ged.versions | GedAdminController@versions | `ged.versions.index` | ged.voir | WCAG 1 | WCAG 0 |
| `/ged/workflows` | ged.workflows | GedAdminController@workflows | `ged.workflows.index` | ged.admin | — | WCAG 0 |

## Module `gouvernance`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/gouvernance/cockpit` | gouvernance.cockpit | GouvernanceController@cockpit | `gouvernance.cockpit` | audit.voir | WCAG 5 | WCAG 0 |

## Module `health`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/health` | sante.health | AdministrationTechniqueController@health | json | — | — | non auditée (json) |

## Module `immobilisations`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/immobilisations` | immobilisations.index | ImmobilisationController@index | `immobilisations.index` | immobilisation.voir | WCAG 4 | WCAG 0 |
| `/immobilisations/create` | immobilisations.create | ImmobilisationController@create | `immobilisations.form` | immobilisation.creer | WCAG 1 | WCAG 0 |
| `/immobilisations/{immobilisation}` | immobilisations.show | ImmobilisationController@show | `immobilisations.show` | immobilisation.voir | WCAG 1 | WCAG 0 |
| `/immobilisations/{immobilisation}/edit` | immobilisations.edit | ImmobilisationController@edit | `immobilisations.form` | immobilisation.modifier | — | — |

## Module `incidents`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/incidents` | incidents.index | IncidentController@index | `incidents.index` | incident.voir | WCAG 5 | WCAG 0 |
| `/incidents/create` | incidents.create | IncidentController@create | `incidents.form` | incident.creer | WCAG 1 | WCAG 0 |
| `/incidents/{incident}` | incidents.show | IncidentController@show | `incidents.show` | incident.voir | WCAG 1 | WCAG 0 |
| `/incidents/{incident}/edit` | incidents.edit | IncidentController@edit | `incidents.form` | incident.modifier | — | — |

## Module `institution`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/institution` | institution.dashboard | InstitutionController@index | `institution.dashboard` | viewAny | WCAG 2 | WCAG 0 |

## Module `login`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/login` | login | LoginController@show | `auth.login` | — | — | — |

## Module `logs`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/logs/connexions` | logs.connexions | LogController@connexions | `logs.connexions` | audit.security.view | — | WCAG 0 |

## Module `marches`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/marches` | marches.index | MarcheController@index | `marches.index` | marche.voir | WCAG 3 | WCAG 0 |
| `/marches/create` | marches.create | MarcheController@create | `marches.form` | marche.creer | WCAG 1 | WCAG 0 |
| `/marches/{marche}` | marches.show | MarcheController@show | `marches.show` | marche.voir | WCAG 1 | WCAG 0 |
| `/marches/{marche}/edit` | marches.edit | MarcheController@edit | `marches.form` | marche.modifier | — | — |

## Module `mes-taches`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/mes-taches` | taches.index | TacheController@index | `taches.index` | — | WCAG 19 | WCAG 0 |
| `/mes-taches/export` | taches.export | TacheController@export | fichier | — | — | non auditée (fichier) |
| `/mes-taches/supervision` | taches.supervision | TacheController@supervision | `taches.supervision` | — | WCAG 4 | WCAG 1 |
| `/mes-taches/{tache}` | taches.show | TacheController@show | `taches.show` | — | WCAG 3 | WCAG 0 |

## Module `mfa`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/mfa` | mfa.setup | MfaController@setup | `auth.mfa-setup` | — | — | — |
| `/mfa/challenge` | mfa.challenge | MfaController@challenge | `auth.mfa-challenge` | — | — | — |

## Module `mon-mot-de-passe`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/mon-mot-de-passe` | password.edit | PasswordController@edit | `auth.password` | — | WCAG 0 | WCAG 0 |

## Module `nomenclature`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/nomenclature` | nomenclature.index | NomenclatureController@index | `nomenclature.index` | viewAny | WCAG 38 | WCAG 24 |
| `/nomenclature/assistant` | nomenclature.assistant | NomenclatureController@assistant | `nomenclature.assistant` | create | WCAG 4 | WCAG 0 |
| `/nomenclature/assistant/contexte` | nomenclature.assistant.contexte | NomenclatureController@contexte | json | create | — | non auditée (json) |
| `/nomenclature/assistant/verifier` | nomenclature.assistant.verifier | NomenclatureController@verifier | json | create | — | non auditée (json) |
| `/nomenclature/tableau-bord` | nomenclature.tableau | NomenclatureController@tableauBord | `nomenclature.tableau` | viewAny | WCAG 3 | WCAG 0 |
| `/nomenclature/{niveau}/create` | nomenclature.create | NomenclatureController@create | `nomenclature.form` | create | WCAG 1 | WCAG 0 |
| `/nomenclature/{niveau}/{id}` | nomenclature.show | NomenclatureController@show | `nomenclature.show` | view | WCAG 1 | WCAG 0 |
| `/nomenclature/{niveau}/{id}/edit` | nomenclature.edit | NomenclatureController@edit | `nomenclature.form` | update | — | — |

## Module `notifications`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/notifications` | notifications.index | NotificationController@index | `notifications.index` | notifications.view | WCAG 131 | WCAG 20 |
| `/notifications/apercu` | notifications.apercu | NotificationController@apercu | json | notifications.view | — | non auditée (json) |
| `/notifications/{id}/ouvrir` | notifications.ouvrir | NotificationController@ouvrir | redirection | notifications.view | — | non auditée (redirection) |

## Module `organigramme`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/organigramme` | organigramme | OrganigrammeController@index | `structures.organigramme` | viewAny | WCAG 7 · débord. mobile 38px | WCAG 6 · débord. mobile 30px |

## Module `paps`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/paps` | paps.index | PapController@index | `paps.index` | viewAny | WCAG 2 | WCAG 0 |
| `/paps/create` | paps.create | PapController@create | `paps.create` | create | — | WCAG 0 |
| `/paps/export/liste` | paps.export.liste | PapExportController@liste | fichier | viewAny, pap.modifier | — | non auditée (fichier) |
| `/paps/pilotage` | paps.dashboard | PapDashboardController@index | `paps.dashboard` | viewAny | WCAG 5 | WCAG 1 |
| `/paps/{pap}` | paps.show | PapController@show | `paps.show` | view, agir | WCAG 3 · débord. tablette 22px | WCAG 0 |
| `/paps/{pap}/edit` | paps.edit | PapController@edit | `paps.edit` | update | — | — |
| `/paps/{pap}/execution-financiere` | paps.execution-financiere | PapIntegrationController@executionFinanciere | `paps.execution-financiere` | view | — | — |
| `/paps/{pap}/historiques/{historique}/snapshot` | paps.snapshot | PapGarController@snapshot | `paps.snapshot` | view | — | — |
| `/paps/{pap}/pdf` | paps.pdf | PapController@pdf | `paps.pdf.fiche` | view | — | — |

## Module `partenaires`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/partenaires` | partenaires.index | PartenaireFinancierController@index | `referentiel.ptf.index` | viewAny | WCAG 3 | WCAG 0 |
| `/partenaires/create` | partenaires.create | PartenaireFinancierController@create | `referentiel.ptf.create` | create | WCAG 1 | WCAG 0 |
| `/partenaires/{partenaire}` | partenaires.show | PartenaireFinancierController@show | `referentiel.ptf.show` | view | WCAG 1 | WCAG 0 |
| `/partenaires/{partenaire}/conventions/create` | conventions.create | ConventionFinancementController@create | `referentiel.conventions.create` | create | — | — |
| `/partenaires/{partenaire}/edit` | partenaires.edit | PartenaireFinancierController@edit | `referentiel.ptf.edit` | update | — | — |

## Module `permissions`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/permissions` | permissions.index | PermissionController@index | `permissions.index` | role.voir | WCAG 43 | WCAG 1 |

## Module `pieces`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/pieces/{piece}/download` | pieces.download | PieceController@download | fichier | — | — | non auditée (fichier) |

## Module `plans-apurement`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/plans-apurement/{planApurement}` | plans-apurement.show | PlanApurementController@show | `plans-apurement.show` | recette.voir | — | — |

## Module `postes`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/postes` | postes.index | PosteController@index | `postes.index` | viewAny | WCAG 1 | WCAG 0 |
| `/postes/create` | postes.create | PosteController@create | `postes.create` | create | — | WCAG 0 |
| `/postes/{poste}/edit` | postes.edit | PosteController@edit | `postes.edit` | update | — | — |

## Module `previsions-recettes`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/previsions-recettes` | previsions-recettes.index | PrevisionRecetteController@index | `recettes.previsions.index` | viewAny | WCAG 23 | WCAG 0 |
| `/previsions-recettes/create` | previsions-recettes.create | PrevisionRecetteController@create | `recettes.previsions.create` | create | WCAG 2 | WCAG 1 |
| `/previsions-recettes/{prevision}` | previsions-recettes.show | PrevisionRecetteController@show | `recettes.previsions.show` | view | WCAG 2 | WCAG 1 |
| `/previsions-recettes/{prevision}/edit` | previsions-recettes.edit | PrevisionRecetteController@edit | `recettes.previsions.edit` | update | — | — |

## Module `profil`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/profil/notifications` | profil.notifications.edit | ProfilNotificationController@edit | `profil.notifications` | notifications.manage | WCAG 2 | WCAG 0 |

## Module `profils`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/profils` | profils.index | ProfilController@index | `profils.index` | viewAny | WCAG 1 | WCAG 0 |
| `/profils/create` | profils.create | ProfilController@create | `profils.create` | create | — | WCAG 0 |
| `/profils/{profil}/edit` | profils.edit | ProfilController@edit | `profils.edit` | update | — | — |

## Module `programmes`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/programmes` | programmes.index | ProgrammeController@index | `programmes.index` | viewAny | WCAG 1 | WCAG 0 |
| `/programmes/create` | programmes.create | ProgrammeController@create | `programmes.create` | create | — | WCAG 0 |
| `/programmes/{programme}/edit` | programmes.edit | ProgrammeController@edit | `programmes.edit` | update | — | — |

## Module `rapports`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/rapports` | rapports.index | RapportController@index | redirection | rapport.voir | — | non auditée (redirection) |
| `/rapports/execution/excel` | rapports.execution.excel | RapportController@executionBudgetaireExcel | redirection | rapport.exporter | — | non auditée (redirection) |
| `/rapports/execution/pdf` | rapports.execution.pdf | RapportController@executionBudgetairePdf | redirection | rapport.exporter | — | non auditée (redirection) |
| `/rapports/pap/pdf` | rapports.pap.pdf | RapportController@papPdf | redirection | rapport.exporter | — | non auditée (redirection) |

## Module `rbm`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/rbm` | rbm.home | Closure@__invoke | closure | — | WCAG 5 | non auditée (closure) |
| `/rbm/arbre/{exercice}` | rbm.arbre | RbmController@arbre | `rbm.arbre` | rbm.voir | WCAG 8 · débord. tablette 12px, mobile 130px | WCAG 6 |
| `/rbm/cadre-logique/{exercice}` | rbm.cadre-logique | RbmModuleController@cadreLogique | `rbm.cadre-logique` | rbm.voir | WCAG 7 · débord. mobile 101px | WCAG 6 |
| `/rbm/cadre-logique/{exercice}/edit` | rbm.cadre-logique.edit | CadreLogiqueController@edit | `rbm.cadre-logique-edit` | update, rbm.voir | — | — |
| `/rbm/catalogues/{catalogue}` | rbm.catalogues.index | RbmCatalogController@index | `rbm.catalogues.index` | viewAny | WCAG 3 | WCAG 1 |
| `/rbm/catalogues/{catalogue}/create` | rbm.catalogues.create | RbmCatalogController@create | `rbm.catalogues.form` | create | — | — |
| `/rbm/catalogues/{catalogue}/{item}/edit` | rbm.catalogues.edit | RbmCatalogController@edit | `rbm.catalogues.form` | update | — | — |
| `/rbm/chaine-resultats/{exercice}` | rbm.chaine-resultats | RbmModuleController@chaineResultats | `rbm.chaine-resultats` | rbm.voir | WCAG 7 · débord. mobile 101px | WCAG 6 |
| `/rbm/export/archiver/{exercice}/{jeu}` | rbm.export.archiver | RbmExportController@archiver | fichier | rbm.exporter | — | non auditée (fichier) |
| `/rbm/export/cadre-logique/{exercice}` | rbm.export.cadre-logique | RbmExportController@cadreLogique | fichier | cadre_logique.voir | — | non auditée (fichier) |
| `/rbm/export/indicateurs/{exercice}` | rbm.export.indicateurs | RbmExportController@indicateurs | fichier | indicateur.exporter | — | non auditée (fichier) |
| `/rbm/export/matrice/{exercice}` | rbm.export.matrice | RbmExportController@matrice | fichier | rbm.exporter | — | non auditée (fichier) |
| `/rbm/export/{niveau}/{exercice}` | rbm.export.niveau | RbmExportController@niveau | fichier | rbm.exporter | — | non auditée (fichier) |
| `/rbm/ged/{type}/{id}` | rbm.ged.show | RbmGedController@show | `rbm.ged` | ged.voir | — | — |
| `/rbm/historique/{exercice}` | rbm.historique.index | RbmHistoriqueController@index | `rbm.historique.index` | audit.voir | WCAG 8 · débord. mobile 101px | WCAG 6 |
| `/rbm/historique/{niveau}/{id}` | rbm.historique.noeud | RbmHistoriqueController@noeud | `rbm.historique.noeud` | audit.voir | — | — |
| `/rbm/hub` | rbm.hub | RbmModuleController@hub | `rbm.hub` | rbm.voir | WCAG 5 | WCAG 3 |
| `/rbm/import` | rbm.import.form | RbmImportController@form | `rbm.import` | rbm.importer | — | WCAG 0 |
| `/rbm/import/modele` | rbm.import.modele | RbmImportController@modele | fichier | rbm.importer | — | non auditée (fichier) |
| `/rbm/import/modele-indicateurs` | rbm.import.modele-indicateurs | RbmImportController@modeleIndicateurs | fichier | rbm.importer | — | non auditée (fichier) |
| `/rbm/indicateurs/{indicateur}/edit` | rbm.indicateurs.edit | IndicateurController@edit | `indicateurs.edit` | indicateur.modifier | — | — |
| `/rbm/matrice/{exercice}` | rbm.matrice | RbmModuleController@matrice | `rbm.matrice` | rbm.voir | WCAG 7 · débord. mobile 101px | WCAG 6 |
| `/rbm/pdf/archiver/{exercice}/{jeu}` | rbm.pdf.archiver | RbmPdfController@archiver | vue | rbm.generer_pdf | — | — |
| `/rbm/pdf/cadre-logique/{exercice}` | rbm.pdf.cadre-logique | RbmPdfController@cadreLogique | vue | cadre_logique.voir | — | — |
| `/rbm/pdf/catalogue/{niveau}/{exercice}` | rbm.pdf.catalogue | RbmPdfController@catalogue | vue | rbm.generer_pdf | — | — |
| `/rbm/pdf/indicateurs/{exercice}` | rbm.pdf.indicateurs | RbmPdfController@indicateurs | vue | rbm.generer_pdf | — | — |
| `/rbm/pdf/matrice/{exercice}` | rbm.pdf.matrice | RbmPdfController@matrice | vue | rbm.generer_pdf | — | — |
| `/rbm/pdf/theorie-changement/{exercice}` | rbm.pdf.theorie-changement | RbmPdfController@theorieChangement | vue | rbm.generer_pdf | — | — |
| `/rbm/suivi-indicateurs` | rbm.suivi-indicateurs.hub | SuiviIndicateursController@hub | `rbm.suivi-indicateurs.hub` | indicateur.voir | WCAG 4 · débord. mobile 111px | WCAG 1 |
| `/rbm/synthese/{exercice}/pdf` | rbm.synthese.pdf | RbmModuleController@synthesePdf | `rbm.pdf.synthese` | rbm.generer_pdf | — | — |
| `/rbm/theorie-changement/{exercice}` | rbm.theorie-changement | RbmModuleController@theorieChangement | `rbm.theorie-changement` | rbm.voir | — | — |
| `/rbm/{niveau}/create` | rbm.create | RbmController@create | `rbm.form` | — | — | — |
| `/rbm/{niveau}/{id}/edit` | rbm.edit | RbmController@edit | `rbm.form` | — | — | — |
| `/rbm/{niveau}/{id}/indicateurs` | rbm.indicateurs.index | IndicateurController@index | `indicateurs.index` | indicateur.voir | — | — |

## Module `recettes`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/recettes` | recettes.index | RecetteController@index | `recettes.index` | viewAny | WCAG 26 | WCAG 1 |
| `/recettes/{recette}` | recettes.show | RecetteController@show | `recettes.show` | view | — | — |

## Module `recettes-rapports`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/recettes-rapports` | recettes.rapports.index | RecettesRapportController@index | `recettes.rapports.index` | recette.voir | WCAG 3 | WCAG 1 |
| `/recettes-rapports/arrieres.pdf` | recettes.rapports.arrieres.pdf | RecettesRapportController@arrieresPdf | `recettes.pdf.arrieres` | — | — | — |
| `/recettes-rapports/arrieres.xlsx` | recettes.rapports.arrieres.excel | RecettesRapportController@arrieresExcel | fichier | — | — | non auditée (fichier) |
| `/recettes-rapports/encaissements.csv` | recettes.rapports.encaissements.csv | RecettesRapportController@encaissementsCsv | fichier | — | — | non auditée (fichier) |
| `/recettes-rapports/encaissements.xlsx` | recettes.rapports.encaissements.excel | RecettesRapportController@encaissementsExcel | fichier | — | — | non auditée (fichier) |
| `/recettes-rapports/mobilisation.csv` | recettes.rapports.mobilisation.csv | RecettesRapportController@mobilisationCsv | fichier | — | — | non auditée (fichier) |
| `/recettes-rapports/mobilisation.pdf` | recettes.rapports.mobilisation.pdf | RecettesRapportController@mobilisationPdf | `recettes.pdf.mobilisation` | — | — | — |
| `/recettes-rapports/mobilisation.xlsx` | recettes.rapports.mobilisation.excel | RecettesRapportController@mobilisationExcel | fichier | — | — | non auditée (fichier) |
| `/recettes-rapports/previsions.csv` | recettes.rapports.previsions.csv | RecettesRapportController@previsionsCsv | fichier | — | — | non auditée (fichier) |
| `/recettes-rapports/previsions.xlsx` | recettes.rapports.previsions.excel | RecettesRapportController@previsionsExcel | fichier | — | — | non auditée (fichier) |
| `/recettes-rapports/rapport-financier.docx` | recettes.rapports.financier.word | RecettesRapportController@rapportFinancierWord | vue | — | — | WCAG ? |
| `/recettes-rapports/rapport-financier.pdf` | recettes.rapports.financier.pdf | RecettesRapportController@rapportFinancierPdf | vue | — | — | — |
| `/recettes-rapports/{cle}/archiver/{format}` | recettes.rapports.archiver | RecettesRapportController@archiver | vue | recette.exporter | — | — |

## Module `recommandations`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/recommandations` | recommandations.index | RecommandationController@index | `recommandations.index` | recommandation.voir | WCAG 4 | WCAG 0 |
| `/recommandations/create` | recommandations.create | RecommandationController@create | `recommandations.form` | recommandation.creer | — | WCAG 0 |
| `/recommandations/{recommandation}` | recommandations.show | RecommandationController@show | `recommandations.show` | recommandation.voir | — | WCAG 0 |
| `/recommandations/{recommandation}/edit` | recommandations.edit | RecommandationController@edit | `recommandations.form` | recommandation.modifier | — | — |

## Module `referentiel-financier`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/referentiel-financier` | referentiel.hub | ReferentielFinancierController@hub | `referentiel.hub` | — | WCAG 6 | WCAG 3 |
| `/referentiel-financier/export/conventions` | referentiel.export.conventions | ReferentielFinancierExportController@conventionsExcel | fichier | convention_financement.exporter | — | non auditée (fichier) |
| `/referentiel-financier/export/partenaires` | referentiel.export.partenaires | ReferentielFinancierExportController@partenairesExcel | fichier | partenaire_financier.exporter | — | non auditée (fichier) |
| `/referentiel-financier/export/sources` | referentiel.export.sources | ReferentielFinancierExportController@sourcesExcel | fichier | source_financement.exporter | — | non auditée (fichier) |
| `/referentiel-financier/export/{cle}/archiver` | referentiel.export.archiver | ReferentielFinancierExportController@archiverExcel | fichier | — | — | non auditée (fichier) |
| `/referentiel-financier/pdf/ptf` | referentiel.pdf.ptf | ReferentielFinancierExportController@ptfPdf | vue | — | — | — |
| `/referentiel-financier/pdf/sources` | referentiel.pdf.sources | ReferentielFinancierExportController@sourcesPdf | vue | — | — | — |
| `/referentiel-financier/pdf/synthese` | referentiel.pdf.synthese | ReferentielFinancierExportController@synthesePdf | vue | — | — | — |
| `/referentiel-financier/pdf/{cle}/archiver` | referentiel.pdf.archiver | ReferentielFinancierExportController@archiverPdf | vue | — | — | — |

## Module `reporting`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/reporting` | reporting.index | ReportingController@index | `reporting.index` | viewAny | WCAG 23 | WCAG 1 |
| `/reporting/cockpit` | reporting.cockpit | ReportingController@cockpit | `reporting.cockpit` | reporting.view | WCAG 17 | WCAG 1 |
| `/reporting/cockpit/activites-retard/export/{format}` | reporting.cockpit.retard.export | ReportingController@activitesRetardExport | `reporting.pdf.activites-retard` | reporting.view | — | — |
| `/reporting/cockpit/activites/{activite}` | reporting.cockpit.activite | ReportingController@activiteDetail | `reporting.activite-detail` | rbm.voir | — | — |
| `/reporting/cockpit/live/drilldown` | reporting.cockpit.live.drilldown | CockpitLiveController@drilldown | json | — | — | non auditée (json) |
| `/reporting/cockpit/live/ping` | reporting.cockpit.live.ping | CockpitLiveController@ping | json | — | — | non auditée (json) |
| `/reporting/cockpit/live/snapshot` | reporting.cockpit.live.snapshot | CockpitLiveController@snapshot | json | — | — | non auditée (json) |
| `/reporting/historique` | reporting.historique | ReportingController@historique | `reporting.historique` | viewAny | WCAG 3 | WCAG 0 |
| `/reporting/modeles` | reporting.modeles.index | RapportPersonnaliseController@index | `reporting.modeles.index` | viewAny | WCAG 1 | WCAG 0 |
| `/reporting/modeles/create` | reporting.modeles.create | RapportPersonnaliseController@create | `reporting.modeles.form` | reporting.create | — | — |
| `/reporting/modeles/{rapportPersonnalise}` | reporting.modeles.show | RapportPersonnaliseController@show | `reporting.modeles.show` | — | — | — |
| `/reporting/modeles/{rapportPersonnalise}/edit` | reporting.modeles.edit | RapportPersonnaliseController@edit | `reporting.modeles.form` | reporting.create | — | — |
| `/reporting/modeles/{rapportPersonnalise}/generer` | reporting.modeles.generer | RapportPersonnaliseController@generer | vue | generate | — | — |
| `/reporting/planification` | reporting.planification | ReportingController@planification | `reporting.planification` | viewAny, reporting.generate | — | WCAG 0 |
| `/reporting/rapports/{rapport}/telecharger` | reporting.telecharger | ReportingController@telecharger | fichier | download | — | non auditée (fichier) |
| `/reporting/techniques` | reporting.techniques | ReportingTechniqueController@index | `reporting.techniques.index` | viewAny, admin_technique.view | — | WCAG 0 |
| `/reporting/{type}` | reporting.show | ReportingController@show | `reporting.show` | viewAny | WCAG 17 | WCAG 1 |
| `/reporting/{type}/generer` | reporting.generer | ReportingController@generer | vue | generate | — | — |
| `/reporting/{type}/generer-async` | reporting.generer-async | ReportingController@genererAsync | vue | generate | — | — |

## Module `risques`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/risques` | risques.index | RisqueController@index | `risques.index` | risques.voir | WCAG 6 | WCAG 1 |
| `/risques/cartographie` | risques.cartographie | RisqueController@cartographie | `risques.cartographie` | risques.voir | WCAG 17 | WCAG 16 |
| `/risques/create` | risques.create | RisqueController@create | `risques.form` | risques.creer | — | WCAG 0 |
| `/risques/export/{format}` | risques.export | RisqueController@export | `pdf.risques` | risques.exporter | — | — |
| `/risques/{risque}` | risques.show | RisqueController@show | `risques.show` | risques.voir | WCAG 17 | WCAG 16 |
| `/risques/{risque}/edit` | risques.edit | RisqueController@edit | `risques.form` | risques.modifier | — | — |

## Module `roles`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/roles` | roles.index | RoleController@index | `roles.index` | viewAny | WCAG 2 | WCAG 1 |
| `/roles/create` | roles.create | RoleController@create | `roles.create` | create | — | WCAG 0 |
| `/roles/{role}/edit` | roles.edit | RoleController@edit | `roles.edit` | update | — | — |

## Module `sauvegardes`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/sauvegardes` | sauvegardes.index | SauvegardeController@index | `sauvegardes.index` | — | — | WCAG 0 |
| `/sauvegardes/{backup}/telecharger` | sauvegardes.telecharger | SauvegardeController@telecharger | fichier | admin_technique.backup | — | non auditée (fichier) |

## Module `sources`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/sources` | sources.index | SourceFinancementController@index | `referentiel.sources.index` | viewAny | WCAG 41 · débord. mobile 62px | WCAG 0 |
| `/sources/create` | sources.create | SourceFinancementController@create | `referentiel.sources.create` | create | WCAG 1 | WCAG 0 |
| `/sources/{source}` | sources.show | SourceFinancementController@show | `referentiel.sources.show` | view | WCAG 1 | WCAG 0 |
| `/sources/{source}/edit` | sources.edit | SourceFinancementController@edit | `referentiel.sources.edit` | update | — | — |

## Module `strategie`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/strategie` | strategie.home | RedirectController@__invoke | redirection | — | — | non auditée (redirection) |
| `/strategie/aide` | strategie.aide | StrategieAideController@index | `strategie.aide.index` | viewAny | — | WCAG 55 · débord. mobile 2px |
| `/strategie/aide/manuel.docx` | strategie.aide.word | StrategieAideController@word | vue | viewAny | — | WCAG ? |
| `/strategie/aide/manuel.pdf` | strategie.aide.pdf | StrategieAideController@pdf | fichier | viewAny | — | non auditée (fichier) |
| `/strategie/alignements` | strategie.alignements.index | AlignementStrategiqueController@index | `strategie.alignements.index` | viewAny | — | WCAG 0 |
| `/strategie/alignements/create` | strategie.alignements.create | AlignementStrategiqueController@create | `strategie.alignements.create` | create | — | WCAG 0 |
| `/strategie/dashboard/decisionnel` | strategie.dashboard.decisionnel | StrategieDashboardController@decisionnel | `strategie.dashboard.decisionnel` | viewAny | — | WCAG 0 |
| `/strategie/documents/{piece}/download` | strategie.documents.download | StrategieDocumentController@download | fichier | strategie.view | — | non auditée (fichier) |
| `/strategie/export/coherence.pdf` | strategie.export.coherence.pdf | StrategiePdfController@coherence | vue | strategie.export | — | — |
| `/strategie/export/matrice.pdf` | strategie.export.matrice.pdf | StrategiePdfController@matrice | vue | strategie.export | — | — |
| `/strategie/export/pilotage.pdf` | strategie.export.pilotage.pdf | StrategieDashboardController@pdf | `strategie.pdf.pilotage` | strategie.export | — | — |
| `/strategie/export/synthese.pdf` | strategie.export.pdf | StrategiePdfController@synthese | vue | strategie.export | — | — |
| `/strategie/export/{catalogue}.xlsx` | strategie.export.excel | StrategieExportController@excel | fichier | strategie.export | — | non auditée (fichier) |
| `/strategie/export/{catalogue}/archiver.xlsx` | strategie.export.archiver.excel | StrategieExportController@archiver | fichier | strategie.export | — | non auditée (fichier) |
| `/strategie/export/{rapport}/archiver.pdf` | strategie.export.archiver.pdf | StrategiePdfController@archiver | vue | strategie.export | — | — |
| `/strategie/hub` | strategie.hub | StrategieModuleController@hub | `strategie.hub` | viewAny | — | WCAG 0 |
| `/strategie/indicateurs` | strategie.indicateurs.index | IndicateurStrategiqueController@index | `strategie.indicateurs.index` | viewAny | — | WCAG 0 |
| `/strategie/indicateurs/create` | strategie.indicateurs.create | IndicateurStrategiqueController@create | `strategie.indicateurs.create` | create | — | WCAG 0 |
| `/strategie/indicateurs/{indicateurStrategique}` | strategie.indicateurs.show | IndicateurStrategiqueController@show | `strategie.indicateurs.show` | view | — | WCAG 0 |
| `/strategie/indicateurs/{indicateurStrategique}/edit` | strategie.indicateurs.edit | IndicateurStrategiqueController@edit | `strategie.indicateurs.edit` | update | — | — |
| `/strategie/matrice` | strategie.matrice | StrategieMatriceController@index | `strategie.matrice.index` | viewAny | — | WCAG 0 |
| `/strategie/objectifs` | strategie.objectifs.index | ObjectifStrategiqueController@index | `strategie.objectifs.index` | viewAny | — | WCAG 0 |
| `/strategie/objectifs/create` | strategie.objectifs.create | ObjectifStrategiqueController@create | `strategie.objectifs.create` | create | — | WCAG 0 |
| `/strategie/objectifs/{objectifStrategique}` | strategie.objectifs.show | ObjectifStrategiqueController@show | `strategie.objectifs.show` | view | — | WCAG 0 |
| `/strategie/objectifs/{objectifStrategique}/edit` | strategie.objectifs.edit | ObjectifStrategiqueController@edit | `strategie.objectifs.edit` | update | — | — |
| `/strategie/orientations` | strategie.orientations.index | OrientationStrategiqueController@index | `strategie.orientations.index` | viewAny | — | WCAG 0 |
| `/strategie/orientations/create` | strategie.orientations.create | OrientationStrategiqueController@create | `strategie.orientations.create` | create | — | WCAG 0 |
| `/strategie/orientations/{orientation}` | strategie.orientations.show | OrientationStrategiqueController@show | `strategie.orientations.show` | view | — | WCAG 0 |
| `/strategie/orientations/{orientation}/edit` | strategie.orientations.edit | OrientationStrategiqueController@edit | `strategie.orientations.edit` | update | — | — |
| `/strategie/plans` | strategie.plans.index | PlanStrategiqueController@index | `strategie.plans.index` | viewAny | — | WCAG 0 |
| `/strategie/plans/create` | strategie.plans.create | PlanStrategiqueController@create | `strategie.plans.create` | create | — | WCAG 0 |
| `/strategie/plans/{plan}` | strategie.plans.show | PlanStrategiqueController@show | `strategie.plans.show` | view | — | WCAG 0 |
| `/strategie/plans/{plan}/edit` | strategie.plans.edit | PlanStrategiqueController@edit | `strategie.plans.edit` | update | — | — |
| `/strategie/pont-pap` | strategie.pont-pap | StrategiePontPapController@index | `strategie.pont-pap.index` | viewAny | — | WCAG 0 |
| `/strategie/pont-rbm` | strategie.pont-rbm | StrategiePontRbmController@index | `strategie.pont-rbm.index` | viewAny | — | WCAG 0 |
| `/strategie/portefeuilles` | strategie.portefeuilles.index | PortefeuilleProjetController@index | `strategie.portefeuilles.index` | viewAny | — | WCAG 0 |
| `/strategie/portefeuilles/create` | strategie.portefeuilles.create | PortefeuilleProjetController@create | `strategie.portefeuilles.create` | create | — | WCAG 0 |
| `/strategie/portefeuilles/{portefeuille}` | strategie.portefeuilles.show | PortefeuilleProjetController@show | `strategie.portefeuilles.show` | view | — | WCAG 0 |
| `/strategie/portefeuilles/{portefeuille}/edit` | strategie.portefeuilles.edit | PortefeuilleProjetController@edit | `strategie.portefeuilles.edit` | update | — | — |
| `/strategie/programmations-annuelles` | strategie.programmations-annuelles.index | ProgrammationAnnuelleController@index | `strategie.programmations-annuelles.index` | viewAny | — | WCAG 0 |
| `/strategie/programmations-annuelles/create` | strategie.programmations-annuelles.create | ProgrammationAnnuelleController@create | `strategie.programmations-annuelles.create` | create | — | WCAG 0 |
| `/strategie/programmations-annuelles/{programmationAnnuelle}` | strategie.programmations-annuelles.show | ProgrammationAnnuelleController@show | `strategie.programmations-annuelles.show` | view | — | WCAG 0 |
| `/strategie/programmations-annuelles/{programmationAnnuelle}/edit` | strategie.programmations-annuelles.edit | ProgrammationAnnuelleController@edit | `strategie.programmations-annuelles.edit` | update | — | — |
| `/strategie/programmations-pluri` | strategie.programmations-pluri.index | ProgrammationPluriannuelleController@index | `strategie.programmations-pluri.index` | viewAny | — | WCAG 0 |
| `/strategie/programmations-pluri/create` | strategie.programmations-pluri.create | ProgrammationPluriannuelleController@create | `strategie.programmations-pluri.create` | create | — | WCAG 0 |
| `/strategie/programmations-pluri/{programmationPluriannuelle}` | strategie.programmations-pluri.show | ProgrammationPluriannuelleController@show | `strategie.programmations-pluri.show` | view | — | WCAG 0 |
| `/strategie/programmations-pluri/{programmationPluriannuelle}/edit` | strategie.programmations-pluri.edit | ProgrammationPluriannuelleController@edit | `strategie.programmations-pluri.edit` | update | — | — |
| `/strategie/programmes` | strategie.programmes.index | ProgrammeStrategiqueController@index | `strategie.programmes.index` | viewAny | — | WCAG 0 |
| `/strategie/programmes/create` | strategie.programmes.create | ProgrammeStrategiqueController@create | `strategie.programmes.create` | create | — | WCAG 0 |
| `/strategie/programmes/{programmeStrategique}` | strategie.programmes.show | ProgrammeStrategiqueController@show | `strategie.programmes.show` | view | — | WCAG 0 |
| `/strategie/programmes/{programmeStrategique}/edit` | strategie.programmes.edit | ProgrammeStrategiqueController@edit | `strategie.programmes.edit` | update | — | — |
| `/strategie/projets` | strategie.projets.index | ProjetStrategiqueController@index | `strategie.projets.index` | viewAny | — | WCAG 0 |
| `/strategie/projets/create` | strategie.projets.create | ProjetStrategiqueController@create | `strategie.projets.create` | create | — | WCAG 0 |
| `/strategie/projets/{projet}` | strategie.projets.show | ProjetStrategiqueController@show | `strategie.projets.show` | view | — | WCAG 0 |
| `/strategie/projets/{projet}/edit` | strategie.projets.edit | ProjetStrategiqueController@edit | `strategie.projets.edit` | update | — | — |
| `/strategie/rapport/coherence` | strategie.rapport.coherence | StrategieRapportController@coherence | `strategie.rapport.coherence` | viewAny | — | WCAG 0 |
| `/strategie/risques` | strategie.risques.index | RisqueStrategiqueController@index | `strategie.risques.index` | viewAny | — | WCAG 0 |
| `/strategie/risques/create` | strategie.risques.create | RisqueStrategiqueController@create | `strategie.risques.create` | create | — | WCAG 0 |
| `/strategie/risques/{risqueStrategique}` | strategie.risques.show | RisqueStrategiqueController@show | `strategie.risques.show` | view | — | WCAG 0 |
| `/strategie/risques/{risqueStrategique}/edit` | strategie.risques.edit | RisqueStrategiqueController@edit | `strategie.risques.edit` | update | — | — |
| `/strategie/visions` | strategie.visions.index | VisionController@index | `strategie.visions.index` | viewAny | — | WCAG 0 |
| `/strategie/visions/create` | strategie.visions.create | VisionController@create | `strategie.visions.create` | create | — | WCAG 0 |
| `/strategie/visions/{vision}` | strategie.visions.show | VisionController@show | `strategie.visions.show` | view | — | WCAG 0 |
| `/strategie/visions/{vision}/edit` | strategie.visions.edit | VisionController@edit | `strategie.visions.edit` | update | — | — |

## Module `structure-budget`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/structure-budget` | structure-budget.arbre | StructureBudgetController@arbre | `structure-budget.arbre` | viewAny | WCAG 9 · débord. tablette 167px, mobile 285px | WCAG 6 |
| `/structure-budget/arbre-complet` | structure-budget.arbre-complet | StructureBudgetController@arbreComplet | `structure-budget.arbre-complet` | viewAny | WCAG 9 | WCAG 5 |
| `/structure-budget/assistant` | structure-budget.assistant | StructureBudgetController@assistant | redirection | — | — | non auditée (redirection) |
| `/structure-budget/assistant/activites` | structure-budget.assistant.activites | StructureBudgetController@activitesDeStructure | json | create | — | non auditée (json) |
| `/structure-budget/export` | structure-budget.export | StructureBudgetImportExportController@export | fichier | export | — | non auditée (fichier) |
| `/structure-budget/import-export` | structure-budget.import-export | StructureBudgetImportExportController@hub | `structure-budget.import-export` | viewAny, structure_budget.importer, structure_budget.creer, structure_budget.exporter | — | — |
| `/structure-budget/import/modele` | structure-budget.import.modele | StructureBudgetImportExportController@modele | fichier | import | — | non auditée (fichier) |
| `/structure-budget/journal` | structure-budget.journal | StructureBudgetController@journal | `structure-budget.journal.index` | — | WCAG 4 | WCAG 0 |
| `/structure-budget/lignes/{ligne}` | structure-budget.ligne.show | StructureBudgetController@showLigne | `structure-budget.ligne.show` | structureBudget.viewLigne | — | — |
| `/structure-budget/lignes/{ligne}/edit` | structure-budget.ligne.edit | StructureBudgetController@editLigne | `structure-budget.ligne.edit` | structureBudget.updateLigne | — | — |
| `/structure-budget/recherche` | structure-budget.recherche | StructureBudgetController@recherche | `structure-budget.recherche` | viewAny | WCAG 9 | WCAG 3 |
| `/structure-budget/structure/{structure}` | structure-budget.structure | StructureBudgetController@structure | `structure-budget.structure` | viewAny | — | — |
| `/structure-budget/tableau-bord` | structure-budget.tableau | StructureBudgetController@tableauBord | `structure-budget.tableau` | viewAny, structure_budget.modifier | WCAG 2 | WCAG 0 |
| `/structure-budget/wizard` | structure-budget.wizard | StructureBudgetController@wizard | `structure-budget.wizard` | create | WCAG 7 | WCAG 5 |
| `/structure-budget/wizard/activites` | structure-budget.wizard.activites | StructureBudgetController@wizardActivites | json | create | — | non auditée (json) |
| `/structure-budget/wizard/contexte` | structure-budget.wizard.contexte | StructureBudgetController@wizardContexte | json | create | — | non auditée (json) |
| `/structure-budget/wizard/rubrique/{rubrique}` | structure-budget.wizard.rubrique | StructureBudgetController@wizardRubrique | json | create | — | non auditée (json) |
| `/structure-budget/wizard/verifier` | structure-budget.wizard.verifier | StructureBudgetController@wizardVerifier | json | create | — | non auditée (json) |

## Module `structures`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/structures` | structures.index | StructureController@index | `structures.index` | viewAny | WCAG 23 · débord. mobile 82px | WCAG 0 |
| `/structures/create` | structures.create | StructureController@create | `structures.create` | create | — | WCAG 0 |
| `/structures/export/annuaire.pdf` | structure.exporter.annuaire.pdf | InstitutionExportController@annuairePdf | vue | — | — | — |
| `/structures/export/annuaire.xlsx` | structure.exporter.annuaire.excel | InstitutionExportController@annuaireExcel | fichier | — | — | non auditée (fichier) |
| `/structures/export/annuaire/{format}/archiver` | structure.exporter.annuaire.archiver | InstitutionExportController@archiver | fichier | — | — | non auditée (fichier) |
| `/structures/import/modele.xlsx` | structure.importer.modele | InstitutionImportController@modeleStructures | fichier | — | — | non auditée (fichier) |
| `/structures/{structure}` | structures.show | StructureController@show | `structures.show` | view | WCAG 1 | WCAG 0 |
| `/structures/{structure}/edit` | structures.edit | StructureController@edit | `structures.edit` | update | — | — |

## Module `suivi`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/suivi` | suivi.index | CampagneSuiviController@index | `suivi.index` | viewAny | WCAG 1 | WCAG 0 |
| `/suivi/create` | suivi.create | CampagneSuiviController@create | `suivi.create` | create | — | WCAG 0 |
| `/suivi/{campagne}/export/collectes/{format}` | suivi.export.collectes | SuiviExportController@collectes | vue | export | — | — |
| `/suivi/{campagne}/export/collectes/{format}/archiver` | suivi.export.collectes.archiver | SuiviExportController@archiver | vue | export | — | — |
| `/suivi/{suivi}` | suivi.show | CampagneSuiviController@show | `suivi.show` | view | — | WCAG 0 |

## Module `suivi-evaluation`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/suivi-evaluation` | suivi-evaluation.index | SuiviEvaluationController@index | `suivi-evaluation.index` | — | WCAG 6 | WCAG 3 |
| `/suivi-evaluation/evaluations/{evaluation}` | suivi-evaluation.evaluations.show | SuiviEvaluationController@showEvaluation | `suivi-evaluation.evaluations.show` | — | — | — |
| `/suivi-evaluation/missions` | suivi-evaluation.missions.index | MissionSuiviController@index | `suivi-evaluation.missions.index` | — | WCAG 2 | WCAG 0 |
| `/suivi-evaluation/missions/create` | suivi-evaluation.missions.create | MissionSuiviController@create | `suivi-evaluation.missions.form` | — | — | WCAG 0 |
| `/suivi-evaluation/missions/{mission}` | suivi-evaluation.missions.show | MissionSuiviController@show | `suivi-evaluation.missions.show` | — | — | WCAG 0 |
| `/suivi-evaluation/plans` | suivi-evaluation.plans.index | PlanAmeliorationController@index | `suivi-evaluation.plans.index` | — | WCAG 2 | WCAG 0 |
| `/suivi-evaluation/plans/create` | suivi-evaluation.plans.create | PlanAmeliorationController@create | `suivi-evaluation.plans.form` | — | — | WCAG 0 |
| `/suivi-evaluation/plans/{plan}` | suivi-evaluation.plans.show | PlanAmeliorationController@show | `suivi-evaluation.plans.show` | — | — | WCAG 0 |

## Module `suivi-financier`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/suivi-financier` | suivi-financier.index | SuiviFinancierController@index | `suivi-financier.index` | — | WCAG 9 | WCAG 1 |
| `/suivi-financier/anomalies` | suivi-financier.anomalies | SuiviFinancierController@anomalies | `suivi-financier.anomalies` | — | WCAG 2 | WCAG 0 |

## Module `tableaux-bord`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/tableaux-bord` | tableaux.index | TableauBordController@index | `tableaux.index` | tableau_bord.voir, recette.voir | WCAG 6 · JS 1 | WCAG 0 |
| `/tableaux-bord/tresorerie` | tableaux.tresorerie | TableauBordController@tresorerie | `tableaux.tresorerie` | tableau_bord.voir | WCAG 7 | WCAG 2 |

## Module `utilisateurs`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/utilisateurs` | utilisateurs.index | UserController@index | `utilisateurs.index` | viewAny | WCAG 20 · débord. mobile 72px | WCAG 0 |
| `/utilisateurs/create` | utilisateurs.create | UserController@create | `utilisateurs.create` | create | — | WCAG 0 |
| `/utilisateurs/{utilisateur}` | utilisateurs.show | UserController@show | `utilisateurs.show` | view | — | WCAG 0 |
| `/utilisateurs/{utilisateur}/edit` | utilisateurs.edit | UserController@edit | `utilisateurs.edit` | update | — | — |
| `/utilisateurs/{utilisateur}/perimetres` | utilisateurs.scopes.edit | UserController@editScopes | `utilisateurs.scopes` | update | — | — |

## Module `verifier`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/verifier/ged/{uuid}` | verification.ged | GedAdminController@verifier | `ged.signatures.verifier` | — | — | — |
| `/verifier/rapport/{token}` | verification.rapport | VerificationController@show | `reporting.verification` | — | — | — |

## Module `workflow`

| Route | Nom | Contrôleur | Vue(s) | Droits déclarés | Avant | Après |
|---|---|---|---|---|---|---|
| `/workflow/audit` | workflow.audit | WorkflowAuditController@index | `workflow.audit.index` | workflow.audit | WCAG 3 | WCAG 0 |
| `/workflow/dashboard` | workflow.dashboard | WorkflowDashboardController@index | `workflow.dashboard.index` | workflow.view | WCAG 4 | WCAG 6 |
| `/workflow/dashboard/export` | workflow.dashboard.export | WorkflowDashboardController@export | vue | workflow.audit | — | — |
| `/workflow/validations` | workflow.validations | WorkflowCorbeilleController@index | `workflow.validations.index` | workflow.validate | — | WCAG 0 · débord. mobile 20px |
