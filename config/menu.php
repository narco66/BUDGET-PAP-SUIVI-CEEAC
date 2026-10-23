<?php

/**
 * Menu sidebar principal - source unique.
 *
 * Types: section | group | link
 * Visibilite: permission (can) | permissions (canany) | null (tous les authentifies)
 *
 * Reorganisation cible:
 * - les routes existantes sont conservees;
 * - les permissions existantes pilotent toujours la visibilite;
 * - les grands domaines suivent la chaine metier Budget/PAP/Performance/CEEAC.
 */
return [

    'items' => [

        ['type' => 'section', 'label' => 'Pilotage'],
        ['type' => 'link', 'label' => 'Accueil institutionnel', 'icon' => 'bi-house-door', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['type' => 'group', 'label' => 'Tableaux de bord', 'icon' => 'bi-speedometer2', 'permissions' => ['tableau_bord.voir', 'rapport.voir', 'reporting.view', 'strategie.view', 'pap.voir', 'admin_technique.view', 'admin_technique.monitor'], 'children' => [
            ['type' => 'link', 'label' => 'Executif', 'icon' => 'bi-grid-1x2', 'route' => 'reporting.cockpit', 'active' => 'reporting.cockpit', 'permissions' => ['reporting.view', 'tableau_bord.voir', 'rapport.voir']],
            ['type' => 'link', 'label' => 'Strategique', 'icon' => 'bi-compass', 'route' => 'strategie.dashboard.decisionnel', 'active' => 'strategie.dashboard.decisionnel', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Financier', 'icon' => 'bi-cash-stack', 'route' => 'tableaux.tresorerie', 'active' => 'tableaux.tresorerie', 'permission' => 'tableau_bord.voir'],
            ['type' => 'link', 'label' => 'Operationnel', 'icon' => 'bi-bar-chart-line', 'route' => 'tableaux.index', 'active' => 'tableaux.index', 'permission' => 'tableau_bord.voir'],
            ['type' => 'link', 'label' => 'PAP', 'icon' => 'bi-clipboard-pulse', 'route' => 'paps.dashboard', 'permission' => 'pap.voir', 'active_rules' => ['routes' => ['paps.dashboard', 'paps.execution-financiere']]],
            ['type' => 'link', 'label' => 'Technique', 'icon' => 'bi-hdd-network', 'route' => 'administration-technique.cockpit', 'active' => 'administration-technique.cockpit', 'permissions' => ['admin_technique.view', 'admin_technique.monitor']],
        ]],

        ['type' => 'section', 'label' => 'Referentiels'],
        ['type' => 'group', 'label' => 'Institutionnel', 'icon' => 'bi-building', 'permission' => 'structure.voir', 'children' => [
            ['type' => 'link', 'label' => 'Structures', 'icon' => 'bi-diagram-3', 'route' => 'structures.index', 'active' => 'structures.*', 'permission' => 'structure.voir'],
            ['type' => 'link', 'label' => 'Organigramme', 'icon' => 'bi-diagram-2', 'route' => 'organigramme', 'active' => 'organigramme', 'permission' => 'structure.voir'],
            ['type' => 'link', 'label' => 'Programmes institutionnels', 'icon' => 'bi-collection', 'route' => 'programmes.index', 'active' => 'programmes.*', 'permission' => 'structure.voir'],
            ['type' => 'link', 'label' => 'Fonctions', 'icon' => 'bi-person-badge', 'route' => 'fonctions.index', 'active' => 'fonctions.*', 'permission' => 'structure.voir'],
            ['type' => 'link', 'label' => 'Postes', 'icon' => 'bi-briefcase', 'route' => 'postes.index', 'active' => 'postes.*', 'permission' => 'structure.voir'],
        ]],
        ['type' => 'group', 'label' => 'RBM / GAR', 'icon' => 'bi-diagram-3-fill', 'permission' => 'rbm.voir', 'children' => [
            ['type' => 'link', 'label' => 'Chaine RBM / GAR', 'icon' => 'bi-diagram-3-fill', 'route' => 'rbm.home', 'active' => 'rbm.*', 'permission' => 'rbm.voir'],
        ]],
        ['type' => 'group', 'label' => 'Financier', 'icon' => 'bi-journal-bookmark', 'permissions' => ['exercice.voir', 'source_financement.voir', 'partenaire_financier.voir', 'nomenclature.voir', 'structure_budget.voir'], 'children' => [
            ['type' => 'link', 'label' => 'Exercices, sources et partenaires', 'icon' => 'bi-journal-bookmark', 'route' => 'referentiel.hub', 'permissions' => ['exercice.voir', 'source_financement.voir', 'partenaire_financier.voir'], 'active_rules' => ['routes' => ['referentiel.*', 'exercices.*', 'sources.*', 'partenaires.*', 'conventions.*']]],
            ['type' => 'link', 'label' => 'Nomenclature budgetaire', 'icon' => 'bi-list-nested', 'route' => 'nomenclature.index', 'active' => 'nomenclature.*', 'permission' => 'nomenclature.voir'],
            ['type' => 'link', 'label' => 'Structure du budget', 'icon' => 'bi-diagram-3', 'route' => 'structure-budget.arbre', 'active' => 'structure-budget.*', 'permission' => 'structure_budget.voir'],
        ]],

        ['type' => 'section', 'label' => 'Planification et PAP'],
        ['type' => 'group', 'label' => 'Planification strategique', 'icon' => 'bi-compass', 'permission' => 'strategie.view', 'children' => [
            ['type' => 'link', 'label' => 'Hub strategique', 'icon' => 'bi-compass', 'route' => 'strategie.hub', 'active' => 'strategie.hub', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Vision', 'icon' => 'bi-eye', 'route' => 'strategie.visions.index', 'active' => 'strategie.visions.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Orientations strategiques', 'icon' => 'bi-signpost-2', 'route' => 'strategie.orientations.index', 'active' => 'strategie.orientations.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Plans strategiques', 'icon' => 'bi-map', 'route' => 'strategie.plans.index', 'active' => 'strategie.plans.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Programmes', 'icon' => 'bi-kanban', 'route' => 'strategie.programmes.index', 'active' => 'strategie.programmes.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Projets', 'icon' => 'bi-folder-check', 'route' => 'strategie.projets.index', 'active' => 'strategie.projets.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Portefeuilles de projets', 'icon' => 'bi-briefcase', 'route' => 'strategie.portefeuilles.index', 'active' => 'strategie.portefeuilles.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Programmation pluriannuelle', 'icon' => 'bi-calendar3-range', 'route' => 'strategie.programmations-pluri.index', 'active' => 'strategie.programmations-pluri.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Programmation annuelle', 'icon' => 'bi-calendar3', 'route' => 'strategie.programmations-annuelles.index', 'active' => 'strategie.programmations-annuelles.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Alignements strategiques', 'icon' => 'bi-bezier2', 'route' => 'strategie.alignements.index', 'active' => 'strategie.alignements.*', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Coherence strategique', 'icon' => 'bi-check2-circle', 'route' => 'strategie.rapport.coherence', 'active' => 'strategie.rapport.coherence', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Pont RBM', 'icon' => 'bi-link-45deg', 'route' => 'strategie.pont-rbm', 'active' => 'strategie.pont-rbm', 'permission' => 'strategie.view'],
            ['type' => 'link', 'label' => 'Pont PAP', 'icon' => 'bi-link', 'route' => 'strategie.pont-pap', 'active' => 'strategie.pont-pap', 'permission' => 'strategie.view'],
        ]],
        ['type' => 'group', 'label' => 'Plan annuel de performance', 'icon' => 'bi-bullseye', 'permission' => 'pap.voir', 'children' => [
            ['type' => 'link', 'label' => 'Elaboration et workflow PAP', 'icon' => 'bi-bullseye', 'route' => 'paps.index', 'permission' => 'pap.voir', 'active_rules' => ['routes' => ['paps.index', 'paps.show', 'paps.create', 'paps.edit']]],
            ['type' => 'link', 'label' => 'Performance PAP', 'icon' => 'bi-clipboard-pulse', 'route' => 'paps.dashboard', 'permission' => 'pap.voir', 'active_rules' => ['routes' => ['paps.dashboard', 'paps.execution-financiere']]],
        ]],

        ['type' => 'section', 'label' => 'Budget et ressources'],
        ['type' => 'group', 'label' => 'Gestion budgetaire', 'icon' => 'bi-wallet2', 'permissions' => ['ligne_budgetaire.voir', 'structure_budget.voir', 'suivi_financier.view', 'tableau_bord.voir'], 'children' => [
            ['type' => 'link', 'label' => 'Preparation et enveloppes', 'icon' => 'bi-sliders2', 'route' => 'budget.enveloppe.index', 'active' => 'budget.enveloppe.*', 'permission' => 'ligne_budgetaire.voir'],
            ['type' => 'link', 'label' => 'Budget et credits', 'icon' => 'bi-cash-stack', 'route' => 'budget.index', 'permission' => 'ligne_budgetaire.voir', 'active_rules' => ['routes' => ['budget.index', 'budget.create', 'budget.edit', 'budget.import.*']]],
            ['type' => 'link', 'label' => 'Suivi financier', 'icon' => 'bi-graph-up-arrow', 'route' => 'suivi-financier.index', 'active' => 'suivi-financier.*', 'permissions' => ['suivi_financier.view', 'tableau_bord.voir']],
        ]],
        ['type' => 'group', 'label' => 'Gestion des recettes', 'icon' => 'bi-piggy-bank', 'permission' => 'recette.voir', 'children' => [
            ['type' => 'link', 'label' => 'Previsions et recettes', 'icon' => 'bi-piggy-bank', 'route' => 'recettes.index', 'permission' => 'recette.voir', 'active_rules' => ['routes' => ['recettes.index', 'recettes.show', 'previsions-recettes.*']]],
            ['type' => 'link', 'label' => 'Contributions et appels de fonds', 'icon' => 'bi-send-check', 'route' => 'appels-fonds.index', 'active' => 'appels-fonds.*', 'permission' => 'recette.voir'],
            ['type' => 'link', 'label' => 'Encaissements', 'icon' => 'bi-bank', 'route' => 'encaissements.index', 'active' => 'encaissements.*', 'permission' => 'recette.voir'],
            ['type' => 'link', 'label' => 'Etats des recettes', 'icon' => 'bi-file-earmark-bar-graph', 'route' => 'recettes.rapports.index', 'active' => 'recettes.rapports.*', 'permission' => 'recette.voir'],
        ]],

        ['type' => 'section', 'label' => 'Execution'],
        ['type' => 'group', 'label' => 'Chaine de la depense', 'icon' => 'bi-receipt', 'permissions' => ['besoin.voir', 'engagement.voir', 'liquidation.voir', 'ordonnancement.voir', 'paiement.voir'], 'children' => [
            ['type' => 'link', 'label' => 'Cockpit de la depense', 'icon' => 'bi-speedometer2', 'route' => 'depense.cockpit', 'active' => 'depense.cockpit', 'permission' => 'engagement.voir'],
            ['type' => 'link', 'label' => 'Expressions du besoin', 'icon' => 'bi-clipboard-plus', 'route' => 'besoins.index', 'active' => 'besoins.*', 'permission' => 'besoin.voir'],
            ['type' => 'link', 'label' => 'Engagements', 'icon' => 'bi-receipt', 'route' => 'engagements.index', 'active' => 'engagements.*', 'permission' => 'engagement.voir'],
            ['type' => 'link', 'label' => 'Liquidations', 'icon' => 'bi-receipt-cutoff', 'route' => 'depense.index', 'route_params' => ['liquidation'], 'permission' => 'liquidation.voir', 'active_rules' => ['routes' => ['depense.*'], 'segment' => [2 => 'liquidation']]],
            ['type' => 'link', 'label' => 'Ordonnancements', 'icon' => 'bi-pen', 'route' => 'depense.index', 'route_params' => ['ordonnancement'], 'permission' => 'ordonnancement.voir', 'active_rules' => ['routes' => ['depense.*'], 'segment' => [2 => 'ordonnancement']]],
            ['type' => 'link', 'label' => 'Paiements', 'icon' => 'bi-cash-coin', 'route' => 'depense.index', 'route_params' => ['paiement'], 'permission' => 'paiement.voir', 'active_rules' => ['routes' => ['depense.*'], 'segment' => [2 => 'paiement']]],
            ['type' => 'link', 'label' => 'Rapprochement', 'icon' => 'bi-bank2', 'route' => 'depense.rapprochement.index', 'active' => 'depense.rapprochement.*', 'permissions' => ['paiement.voir', 'liquidation.voir']],
        ]],
        ['type' => 'group', 'label' => 'Marches et contrats', 'icon' => 'bi-briefcase', 'permissions' => ['marche.voir', 'contrat.voir'], 'children' => [
            ['type' => 'link', 'label' => 'Marches / PPM', 'icon' => 'bi-briefcase', 'route' => 'marches.index', 'active' => 'marches.*', 'permission' => 'marche.voir'],
            ['type' => 'link', 'label' => 'Contrats', 'icon' => 'bi-file-earmark-text', 'route' => 'contrats.index', 'active' => 'contrats.*', 'permission' => 'contrat.voir'],
        ]],

        ['type' => 'section', 'label' => 'Suivi et analyse'],
        ['type' => 'group', 'label' => 'Suivi et performance', 'icon' => 'bi-clipboard-data', 'permissions' => ['suivi_evaluation.view', 'suivi.voir', 'recommandation.voir', 'formation.voir', 'immobilisation.voir'], 'children' => [
            ['type' => 'link', 'label' => 'Suivi-evaluation', 'icon' => 'bi-clipboard-data', 'route' => 'suivi-evaluation.index', 'active' => 'suivi-evaluation.*', 'permissions' => ['suivi_evaluation.view', 'suivi.voir', 'recommandation.voir']],
            ['type' => 'link', 'label' => 'Campagnes de suivi', 'icon' => 'bi-calendar-check', 'route' => 'suivi.index', 'permission' => 'suivi.voir', 'active_rules' => ['routes' => ['suivi.*'], 'exclude_routes' => ['suivi-evaluation.*']]],
            ['type' => 'link', 'label' => 'Recommandations', 'icon' => 'bi-check2-square', 'route' => 'recommandations.index', 'active' => 'recommandations.*', 'permission' => 'recommandation.voir'],
            ['type' => 'link', 'label' => 'Formations et ateliers', 'icon' => 'bi-mortarboard', 'route' => 'formations.index', 'active' => 'formations.*', 'permission' => 'formation.voir'],
            ['type' => 'link', 'label' => 'Immobilisations', 'icon' => 'bi-box-seam', 'route' => 'immobilisations.index', 'active' => 'immobilisations.*', 'permission' => 'immobilisation.voir'],
        ]],
        ['type' => 'group', 'label' => 'Reporting et analyse', 'icon' => 'bi-journals', 'permissions' => ['tableau_bord.voir', 'rapport.voir', 'reporting.view'], 'children' => [
            ['type' => 'link', 'label' => 'Rapports et reporting', 'icon' => 'bi-journals', 'route' => 'reporting.index', 'permission' => 'rapport.voir', 'active_rules' => ['routes' => ['reporting.*', 'rapports.*'], 'exclude' => ['reporting.cockpit', 'reporting.historique']]],
            ['type' => 'link', 'label' => 'Rapports personnalises', 'icon' => 'bi-sliders', 'route' => 'reporting.modeles.index', 'active' => 'reporting.modeles.*', 'permission' => 'reporting.view'],
            ['type' => 'link', 'label' => 'Centre d exports', 'icon' => 'bi-box-arrow-down', 'route' => 'reporting.historique', 'active' => 'reporting.historique', 'permission' => 'rapport.voir'],
        ]],

        ['type' => 'section', 'label' => 'Collaboration'],
        ['type' => 'group', 'label' => 'Gestion documentaire', 'icon' => 'bi-folder2-open', 'permission' => 'ged.voir', 'children' => [
            ['type' => 'link', 'label' => 'Tableau de bord GED', 'icon' => 'bi-folder2-open', 'route' => 'ged.dashboard', 'active' => 'ged.dashboard', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Documents', 'icon' => 'bi-files', 'route' => 'ged.documents.index', 'permission' => 'ged.voir', 'active_rules' => ['routes' => ['ged.documents.*'], 'exclude' => ['ged.documents.confidentiels', 'ged.documents.expires']]],
            ['type' => 'link', 'label' => 'Depot documentaire', 'icon' => 'bi-cloud-upload', 'route' => 'ged.documents.create', 'active' => 'ged.documents.create', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Recherche avancee', 'icon' => 'bi-search', 'route' => 'ged.recherche', 'active' => 'ged.recherche', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Classement', 'icon' => 'bi-diagram-2', 'route' => 'ged.classement', 'active' => 'ged.classement', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Categories', 'icon' => 'bi-tags', 'route' => 'ged.categories', 'active' => 'ged.categories', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Types de documents', 'icon' => 'bi-file-earmark', 'route' => 'ged.types', 'active' => 'ged.types', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Dossiers', 'icon' => 'bi-folder', 'route' => 'ged.dossiers', 'active' => 'ged.dossiers', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Versions', 'icon' => 'bi-layers', 'route' => 'ged.versions', 'active' => 'ged.versions', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Signatures', 'icon' => 'bi-pen', 'route' => 'ged.signatures.index', 'active' => 'ged.signatures.*', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Circuits documentaires', 'icon' => 'bi-signpost-split', 'route' => 'ged.workflows', 'active' => 'ged.workflows', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Documents partages', 'icon' => 'bi-share', 'route' => 'ged.partages', 'active' => 'ged.partages', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Confidentiels', 'icon' => 'bi-shield-lock', 'route' => 'ged.documents.confidentiels', 'active' => 'ged.documents.confidentiels', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Expires', 'icon' => 'bi-hourglass-split', 'route' => 'ged.documents.expires', 'active' => 'ged.documents.expires', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Archives', 'icon' => 'bi-archive', 'route' => 'ged.archives', 'active' => 'ged.archives', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Conservation', 'icon' => 'bi-safe', 'route' => 'ged.conservation', 'active' => 'ged.conservation', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Journal documentaire', 'icon' => 'bi-journal-text', 'route' => 'ged.journal', 'active' => 'ged.journal', 'permission' => 'ged.voir'],
            ['type' => 'link', 'label' => 'Parametres GED', 'icon' => 'bi-gear', 'route' => 'ged.parametres', 'active' => 'ged.parametres', 'permission' => 'ged.admin'],
        ]],
        ['type' => 'group', 'label' => 'Gestion des connaissances', 'icon' => 'bi-book', 'permission' => 'ged.voir', 'children' => [
            ['type' => 'link', 'label' => 'Wiki et procedures', 'icon' => 'bi-journal-bookmark', 'route' => 'ged.connaissances.index', 'active' => 'ged.connaissances.*', 'permission' => 'ged.voir'],
        ]],

        ['type' => 'section', 'label' => 'Gouvernance SI'],
        ['type' => 'group', 'label' => 'Workflow et validation', 'icon' => 'bi-diagram-3', 'permissions' => ['workflow.view', 'workflow.validate', 'workflow.audit', 'workflow.admin'], 'children' => [
            ['type' => 'link', 'label' => 'Performance workflow', 'icon' => 'bi-speedometer', 'route' => 'workflow.dashboard', 'active' => 'workflow.dashboard', 'permission' => 'workflow.view'],
            ['type' => 'link', 'label' => 'Corbeille validations', 'icon' => 'bi-inbox', 'route' => 'workflow.validations', 'active' => 'workflow.validations', 'permission' => 'workflow.validate'],
            ['type' => 'link', 'label' => 'Audit workflow', 'icon' => 'bi-journal-check', 'route' => 'workflow.audit', 'active' => 'workflow.audit', 'permission' => 'workflow.audit'],
            ['type' => 'link', 'label' => 'Circuits workflow', 'icon' => 'bi-diagram-3', 'route' => 'admin.workflows.index', 'active' => 'admin.workflows.*', 'permission' => 'workflow.admin'],
        ]],
        ['type' => 'group', 'label' => 'Notifications et alertes', 'icon' => 'bi-bell', 'permissions' => ['workflow.view', 'notifications.manage', 'notifications.admin'], 'children' => [
            ['type' => 'link', 'label' => 'Notifications', 'icon' => 'bi-bell', 'route' => 'notifications.index', 'active' => 'notifications.*', 'permission' => 'workflow.view'],
            ['type' => 'link', 'label' => 'Alertes', 'icon' => 'bi-exclamation-triangle', 'route' => 'alertes.index', 'active' => 'alertes.*', 'permission' => 'workflow.view'],
            ['type' => 'link', 'label' => 'Preferences notifications', 'icon' => 'bi-sliders', 'route' => 'profil.notifications.edit', 'active' => 'profil.notifications.*', 'permission' => 'notifications.manage'],
            ['type' => 'link', 'label' => 'Modeles e-mail', 'icon' => 'bi-envelope-paper', 'route' => 'admin.notifications.templates.index', 'active' => 'admin.notifications.*', 'permission' => 'notifications.admin'],
        ]],
        ['type' => 'group', 'label' => 'Interoperabilite et API', 'icon' => 'bi-plug', 'permissions' => ['api.view', 'api.monitor', 'api.admin'], 'children' => [
            ['type' => 'link', 'label' => 'Hub interoperabilite', 'icon' => 'bi-plug', 'route' => 'admin.interoperabilite.index', 'active' => 'admin.interoperabilite.*', 'permissions' => ['api.view', 'api.monitor', 'api.admin']],
            ['type' => 'link', 'label' => 'Documentation API', 'icon' => 'bi-file-code', 'route' => 'api.docs', 'active' => 'api.docs', 'permissions' => ['api.view', 'api.admin']],
        ]],
        ['type' => 'group', 'label' => 'Audit, controle et conformite', 'icon' => 'bi-shield-check', 'permissions' => ['audit.voir', 'risques.voir', 'controle_interne.voir', 'conformite.voir'], 'children' => [
            ['type' => 'link', 'label' => 'Cockpit gouvernance', 'icon' => 'bi-shield-check', 'route' => 'gouvernance.cockpit', 'active' => 'gouvernance.*', 'permission' => 'audit.voir'],
            ['type' => 'link', 'label' => 'Missions d audit', 'icon' => 'bi-search', 'route' => 'audit-missions.index', 'active' => 'audit-missions.*', 'permission' => 'audit.voir'],
            ['type' => 'link', 'label' => 'Piste d audit', 'icon' => 'bi-shield-lock', 'route' => 'audit.index', 'active' => 'audit.*', 'permission' => 'audit.voir'],
            ['type' => 'link', 'label' => 'Registre des risques', 'icon' => 'bi-exclamation-triangle', 'route' => 'risques.index', 'active' => 'risques.*', 'permission' => 'risques.voir'],
            ['type' => 'link', 'label' => 'Controle interne COSO', 'icon' => 'bi-ui-checks-grid', 'route' => 'controle-interne.index', 'active' => 'controle-interne.*', 'permission' => 'controle_interne.voir'],
            ['type' => 'link', 'label' => 'Non-conformites', 'icon' => 'bi-clipboard-x', 'route' => 'conformite.index', 'active' => 'conformite.*', 'permission' => 'conformite.voir'],
        ]],

        ['type' => 'section', 'label' => 'Administration'],
        ['type' => 'group', 'label' => 'Utilisateurs et acces', 'icon' => 'bi-people', 'permissions' => ['utilisateur.voir', 'role.voir', 'profil.voir', 'utilisateur.consulter_journal'], 'children' => [
            ['type' => 'link', 'label' => 'Utilisateurs', 'icon' => 'bi-people', 'route' => 'utilisateurs.index', 'active' => 'utilisateurs.*', 'permission' => 'utilisateur.voir'],
            ['type' => 'link', 'label' => 'Roles et permissions', 'icon' => 'bi-person-gear', 'route' => 'roles.index', 'permission' => 'role.voir', 'active_rules' => ['routes' => ['roles.*', 'permissions.*']]],
            ['type' => 'link', 'label' => 'Profils', 'icon' => 'bi-person-vcard', 'route' => 'profils.index', 'active' => 'profils.*', 'permission' => 'profil.voir'],
            ['type' => 'link', 'label' => 'Journal connexions', 'icon' => 'bi-box-arrow-in-right', 'route' => 'logs.connexions', 'active' => 'logs.*', 'permission' => 'utilisateur.consulter_journal'],
        ]],
        ['type' => 'group', 'label' => 'Administration technique', 'icon' => 'bi-hdd-network', 'permissions' => ['admin_technique.view', 'admin_technique.monitor', 'incident.voir', 'admin_technique.backup', 'admin_technique.restore'], 'children' => [
            ['type' => 'link', 'label' => 'Supervision technique', 'icon' => 'bi-hdd-network', 'route' => 'administration-technique.cockpit', 'active' => 'administration-technique.cockpit', 'permissions' => ['admin_technique.view', 'admin_technique.monitor']],
            ['type' => 'link', 'label' => 'Files d attente', 'icon' => 'bi-queue-list', 'route' => 'administration-technique.queues.index', 'active' => 'administration-technique.queues.*', 'permissions' => ['admin_technique.view', 'admin_technique.monitor']],
            ['type' => 'link', 'label' => 'Incidents', 'icon' => 'bi-cone-striped', 'route' => 'incidents.index', 'active' => 'incidents.*', 'permission' => 'incident.voir'],
            ['type' => 'link', 'label' => 'Sauvegardes et restauration', 'icon' => 'bi-database-down', 'route' => 'sauvegardes.index', 'active' => 'sauvegardes.*', 'permissions' => ['admin_technique.backup', 'admin_technique.restore']],
        ]],
    ],

];
