<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\AdministrationTechniqueController;
use App\Http\Controllers\AdministrationTechnique\QueueMonitorController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\ControleInterneController;
use App\Http\Controllers\GouvernanceController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MissionAuditController;
use App\Http\Controllers\NonConformiteController;
use App\Http\Controllers\RisqueController;
use App\Http\Controllers\SauvegardeController;
use App\Http\Controllers\BudgetEnveloppeController;
use App\Http\Controllers\BudgetPreparationController;
use App\Http\Controllers\BudgetRapportController;
use App\Http\Controllers\BudgetRevisionController;
use App\Http\Controllers\BudgetSimulationController;
use App\Http\Controllers\BudgetImportController;
use App\Http\Controllers\CampagneSuiviController;
use App\Http\Controllers\CollecteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BesoinConsolidationController;
use App\Http\Controllers\CockpitDepenseController;
use App\Http\Controllers\DepenseExportController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\EtapeDepenseController;
use App\Http\Controllers\ExerciceController;
use App\Http\Controllers\ExpressionBesoinController;
use App\Http\Controllers\LigneBudgetaireController;
use App\Http\Controllers\StructureBudgetController;
use App\Http\Controllers\StructureBudgetImportExportController;
use App\Http\Controllers\PieceController;
use App\Http\Controllers\FonctionController;
use App\Http\Controllers\PosteController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\ImmobilisationController;
use App\Http\Controllers\IndicateurController;
use App\Http\Controllers\ConventionFinancementController;
use App\Http\Controllers\ContratController;
use App\Http\Controllers\InstitutionExportController;
use App\Http\Controllers\InstitutionImportController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MarcheController;
use App\Http\Controllers\NomenclatureController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AlerteController;
use App\Http\Controllers\AdminEmailTemplateController;
use App\Http\Controllers\Admin\Interop\InteropAppController;
use App\Http\Controllers\Admin\Interop\InteropClientController;
use App\Http\Controllers\Admin\Interop\InteropExportController;
use App\Http\Controllers\Admin\Interop\InteropHubController;
use App\Http\Controllers\Admin\Interop\InteropJournalController;
use App\Http\Controllers\Admin\Interop\InteropMonitoringController;
use App\Http\Controllers\Admin\Interop\InteropWebhookController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ProfilNotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\OrganigrammeController;
use App\Http\Controllers\PartenaireFinancierController;
use App\Http\Controllers\PapDashboardController;
use App\Http\Controllers\PapExportController;
use App\Http\Controllers\PapGarController;
use App\Http\Controllers\PapIntegrationController;
use App\Http\Controllers\PapController;
use App\Http\Controllers\ProgrammeController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\RecetteController;
use App\Http\Controllers\PrevisionRecetteController;
use App\Http\Controllers\EncaissementController;
use App\Http\Controllers\AppelFondsController;
use App\Http\Controllers\RecettesRapportController;
use App\Http\Controllers\PlanApurementController;
use App\Http\Controllers\RecommandationController;
use App\Http\Controllers\ReferentielFinancierController;
use App\Http\Controllers\ReferentielFinancierExportController;
use App\Http\Controllers\Reporting\CockpitLiveController;
use App\Http\Controllers\Reporting\ReportingController;
use App\Http\Controllers\Reporting\ReportingTechniqueController;
use App\Http\Controllers\Reporting\RapportPersonnaliseController;
use App\Http\Controllers\Reporting\VerificationController;
use App\Http\Controllers\Workflow\WorkflowAdminController;
use App\Http\Controllers\Workflow\WorkflowAuditController;
use App\Http\Controllers\Workflow\WorkflowCorbeilleController;
use App\Http\Controllers\Workflow\WorkflowDashboardController;
use App\Http\Controllers\Rbm\RbmCatalogController;
use App\Http\Controllers\Rbm\RbmExportController;
use App\Http\Controllers\Rbm\RbmHistoriqueController;
use App\Http\Controllers\Rbm\RbmImportController;
use App\Http\Controllers\Rbm\CadreLogiqueController;
use App\Http\Controllers\Rbm\RbmDuplicationController;
use App\Http\Controllers\Rbm\RbmGedController;
use App\Http\Controllers\Rbm\RbmModuleController;
use App\Http\Controllers\Rbm\RbmPdfController;
use App\Http\Controllers\Rbm\MesureController;
use App\Http\Controllers\Rbm\SuiviIndicateursController;
use App\Http\Controllers\RbmController;
use App\Http\Controllers\SourceFinancementController;
use App\Http\Controllers\Strategie\IndicateurStrategiqueController;
use App\Http\Controllers\Strategie\StrategieDocumentController;
use App\Http\Controllers\Strategie\ObjectifStrategiqueController;
use App\Http\Controllers\Strategie\RisqueStrategiqueController;
use App\Http\Controllers\Strategie\StrategieDashboardController;
use App\Http\Controllers\Strategie\StrategiePontPapController;
use App\Http\Controllers\Strategie\OrientationStrategiqueController;
use App\Http\Controllers\Strategie\PlanStrategiqueController;
use App\Http\Controllers\Strategie\PortefeuilleProjetController;
use App\Http\Controllers\Strategie\ProgrammationAnnuelleController;
use App\Http\Controllers\Strategie\ProgrammationPluriannuelleController;
use App\Http\Controllers\Strategie\ProgrammeStrategiqueController;
use App\Http\Controllers\Strategie\ProjetStrategiqueController;
use App\Http\Controllers\Strategie\StrategieAideController;
use App\Http\Controllers\Strategie\StrategieArchiveController;
use App\Http\Controllers\Strategie\StrategieExportController;
use App\Http\Controllers\Strategie\StrategieMatriceController;
use App\Http\Controllers\Strategie\StrategieModuleController;
use App\Http\Controllers\Strategie\StrategiePdfController;
use App\Http\Controllers\Strategie\StrategiePontRbmController;
use App\Http\Controllers\Strategie\StrategieRapportController;
use App\Http\Controllers\Strategie\StrategieValidationController;
use App\Http\Controllers\Strategie\AlignementStrategiqueController;
use App\Http\Controllers\Strategie\VisionController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\SuiviFinancierController;
use App\Http\Controllers\MissionSuiviController;
use App\Http\Controllers\PlanAmeliorationController;
use App\Http\Controllers\SuiviEvaluationController;
use App\Http\Controllers\TableauBordController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// --- Authentification ---
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::get('mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('mfa/challenge', [MfaController::class, 'verifyChallenge'])->name('mfa.challenge.verify');
});
Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// --- Application (authentifiée) ---
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // L1 — Référentiel institutionnel (D1)
    Route::get('organigramme', [OrganigrammeController::class, 'index'])->name('organigramme');
    Route::get('institution', [InstitutionController::class, 'index'])->name('institution.dashboard');
    Route::prefix('structures/export')->name('structure.exporter.')->group(function () {
        Route::get('annuaire.xlsx', [InstitutionExportController::class, 'annuaireExcel'])->name('annuaire.excel');
        Route::get('annuaire.pdf', [InstitutionExportController::class, 'annuairePdf'])->name('annuaire.pdf');
        Route::get('annuaire/{format}/archiver', [InstitutionExportController::class, 'archiver'])->name('annuaire.archiver');
    });
    Route::get('structures/import/modele.xlsx', [InstitutionImportController::class, 'modeleStructures'])->name('structure.importer.modele');
    Route::post('structures/import', [InstitutionImportController::class, 'importerStructures'])->name('structure.importer.structures');
    Route::resource('structures', StructureController::class);
    Route::resource('exercices', ExerciceController::class);
    Route::post('exercices/{exercice}/ouvrir', [ExerciceController::class, 'ouvrir'])->name('exercices.ouvrir');
    Route::post('exercices/{exercice}/cloturer', [ExerciceController::class, 'cloturer'])->name('exercices.cloturer');
    Route::post('exercices/{exercice}/activer', [ExerciceController::class, 'activer'])->name('exercices.activer');

    Route::resource('sources', SourceFinancementController::class)->parameters(['sources' => 'source']);
    Route::resource('partenaires', PartenaireFinancierController::class)->parameters(['partenaires' => 'partenaire']);
    Route::get('partenaires/{partenaire}/conventions/create', [ConventionFinancementController::class, 'create'])->name('conventions.create');
    Route::post('conventions', [ConventionFinancementController::class, 'store'])->name('conventions.store');
    Route::get('conventions/{convention}/edit', [ConventionFinancementController::class, 'edit'])->name('conventions.edit');
    Route::put('conventions/{convention}', [ConventionFinancementController::class, 'update'])->name('conventions.update');
    Route::delete('conventions/{convention}', [ConventionFinancementController::class, 'destroy'])->name('conventions.destroy');
    Route::get('conventions/{convention}', [\App\Http\Controllers\DecaissementConventionController::class, 'show'])->name('conventions.show');
    Route::post('conventions/{convention}/decaissements', [\App\Http\Controllers\DecaissementConventionController::class, 'store'])->name('decaissements.store');
    Route::post('decaissements-convention/{decaissement}/annuler', [\App\Http\Controllers\DecaissementConventionController::class, 'annuler'])->name('decaissements.annuler');

    Route::resource('fonctions', FonctionController::class)->except('show');
    Route::resource('postes', PosteController::class)->except('show');
    Route::resource('programmes', ProgrammeController::class)->except('show');
    // Changement de mot de passe (1ʳᵉ connexion / volontaire)
    Route::get('mon-mot-de-passe', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('mon-mot-de-passe', [PasswordController::class, 'update'])->name('password.update');
    Route::get('mfa', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('mfa', [MfaController::class, 'confirmSetup'])->name('mfa.setup.confirm');
    Route::delete('mfa', [MfaController::class, 'disable'])->name('mfa.disable');

    // Module Accès — utilisateurs (actions + périmètres avant la resource)
    Route::post('utilisateurs/{utilisateur}/statut', [UserController::class, 'changerStatut'])->name('utilisateurs.statut');
    Route::post('utilisateurs/{utilisateur}/reinitialiser', [UserController::class, 'reinitialiserMotDePasse'])->name('utilisateurs.reinitialiser');
    Route::get('utilisateurs/{utilisateur}/perimetres', [UserController::class, 'editScopes'])->name('utilisateurs.scopes.edit');
    Route::put('utilisateurs/{utilisateur}/perimetres', [UserController::class, 'updateScopes'])->name('utilisateurs.scopes.update');
    Route::resource('utilisateurs', UserController::class)->parameters(['utilisateurs' => 'utilisateur']);

    // Rôles, profils, matrice des permissions
    Route::resource('roles', RoleController::class)->except('show');
    Route::resource('profils', ProfilController::class)->except('show');
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

    // Journal des connexions
    Route::get('logs/connexions', [LogController::class, 'connexions'])->name('logs.connexions');

    // L1 — Chaîne RBM / GAR (D2) + Module 2 référentiel étendu
    Route::prefix('rbm')->name('rbm.')->group(function () {
        Route::get('hub', [RbmModuleController::class, 'hub'])->name('hub');
        Route::get('suivi-indicateurs', [SuiviIndicateursController::class, 'hub'])->name('suivi-indicateurs.hub');
        Route::get('arbre/{exercice}', [RbmController::class, 'arbre'])->name('arbre');
        Route::get('matrice/{exercice}', [RbmModuleController::class, 'matrice'])->name('matrice');
        Route::get('cadre-logique/{exercice}', [RbmModuleController::class, 'cadreLogique'])->name('cadre-logique');
        Route::get('cadre-logique/{exercice}/edit', [CadreLogiqueController::class, 'edit'])->name('cadre-logique.edit');
        Route::put('cadre-logique/{exercice}', [CadreLogiqueController::class, 'update'])->name('cadre-logique.update');
        Route::get('chaine-resultats/{exercice}', [RbmModuleController::class, 'chaineResultats'])->name('chaine-resultats');
        Route::get('theorie-changement/{exercice}', [RbmModuleController::class, 'theorieChangement'])->name('theorie-changement');
        Route::get('pdf/theorie-changement/{exercice}', [RbmPdfController::class, 'theorieChangement'])->name('pdf.theorie-changement');
        Route::get('synthese/{exercice}/pdf', [RbmModuleController::class, 'synthesePdf'])->name('synthese.pdf');
        Route::get('historique/{exercice}', [RbmHistoriqueController::class, 'index'])->name('historique.index');
        Route::get('historique/{niveau}/{id}', [RbmHistoriqueController::class, 'noeud'])->name('historique.noeud');
        Route::get('export/matrice/{exercice}', [RbmExportController::class, 'matrice'])->name('export.matrice');
        Route::get('export/cadre-logique/{exercice}', [RbmExportController::class, 'cadreLogique'])->name('export.cadre-logique');
        Route::get('export/indicateurs/{exercice}', [RbmExportController::class, 'indicateurs'])->name('export.indicateurs');
        Route::get('export/{niveau}/{exercice}', [RbmExportController::class, 'niveau'])->name('export.niveau');
        Route::get('pdf/matrice/{exercice}', [RbmPdfController::class, 'matrice'])->name('pdf.matrice');
        Route::get('pdf/cadre-logique/{exercice}', [RbmPdfController::class, 'cadreLogique'])->name('pdf.cadre-logique');
        Route::get('pdf/catalogue/{niveau}/{exercice}', [RbmPdfController::class, 'catalogue'])->name('pdf.catalogue');
        Route::get('pdf/indicateurs/{exercice}', [RbmPdfController::class, 'indicateurs'])->name('pdf.indicateurs');
        Route::get('export/archiver/{exercice}/{jeu}', [RbmExportController::class, 'archiver'])->name('export.archiver');
        Route::get('pdf/archiver/{exercice}/{jeu}', [RbmPdfController::class, 'archiver'])->name('pdf.archiver');

        Route::get('ged/{type}/{id}', [RbmGedController::class, 'show'])->name('ged.show');
        Route::post('reporter-exercice', [RbmDuplicationController::class, 'store'])->name('reporter-exercice');

        Route::get('import', [RbmImportController::class, 'form'])->name('import.form');
        Route::post('import', [RbmImportController::class, 'importer'])->name('import.store');
        Route::post('import/indicateurs', [RbmImportController::class, 'importerIndicateurs'])->name('import.indicateurs');
        Route::get('import/modele', [RbmImportController::class, 'modele'])->name('import.modele');
        Route::get('import/modele-indicateurs', [RbmImportController::class, 'modeleIndicateurs'])->name('import.modele-indicateurs');

        Route::get('indicateurs/{indicateur}/edit', [IndicateurController::class, 'edit'])->name('indicateurs.edit');
        Route::put('indicateurs/{indicateur}', [IndicateurController::class, 'update'])->name('indicateurs.update');
        Route::delete('indicateurs/{indicateur}', [IndicateurController::class, 'destroy'])->name('indicateurs.destroy');

        Route::post('indicateurs/{indicateur}/mesures', [MesureController::class, 'store'])->name('mesures.store');
        Route::put('mesures/{mesure}', [MesureController::class, 'update'])->name('mesures.update');
        Route::delete('mesures/{mesure}', [MesureController::class, 'destroy'])->name('mesures.destroy');
        Route::post('mesures/{mesure}/verifier', [MesureController::class, 'verifier'])->name('mesures.verifier');
        Route::post('mesures/{mesure}/valider', [MesureController::class, 'valider'])->name('mesures.valider');

        Route::prefix('catalogues/{catalogue}')->name('catalogues.')->group(function () {
            Route::get('/', [RbmCatalogController::class, 'index'])->name('index');
            Route::get('create', [RbmCatalogController::class, 'create'])->name('create');
            Route::post('/', [RbmCatalogController::class, 'store'])->name('store');
            Route::get('{item}/edit', [RbmCatalogController::class, 'edit'])->name('edit');
            Route::put('{item}', [RbmCatalogController::class, 'update'])->name('update');
            Route::delete('{item}', [RbmCatalogController::class, 'destroy'])->name('destroy');
        });

        Route::get('{niveau}/create', [RbmController::class, 'create'])->name('create');
        Route::post('{niveau}', [RbmController::class, 'store'])->name('store');
        Route::get('{niveau}/{id}/edit', [RbmController::class, 'edit'])->name('edit');
        Route::put('{niveau}/{id}', [RbmController::class, 'update'])->name('update');
        Route::delete('{niveau}/{id}', [RbmController::class, 'destroy'])->name('destroy');
        Route::post('{niveau}/{id}/archiver', [RbmController::class, 'archiver'])->name('archiver');
        Route::post('{niveau}/{id}/restaurer', [RbmController::class, 'restaurer'])->name('restaurer');
        Route::get('{niveau}/{id}/indicateurs', [IndicateurController::class, 'index'])->name('indicateurs.index');
        Route::post('{niveau}/{id}/indicateurs', [IndicateurController::class, 'store'])->name('indicateurs.store');
    });

    // Module Import : lots traçables (analyse, correspondance, simulation, approbation, exécution, rapports).
    Route::prefix('imports')->name('imports.')->controller(\App\Http\Controllers\ImportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('nouveau', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('modeles/{domaine}.xlsx', 'modele')->name('modele');
        Route::get('{lot}', 'show')->whereNumber('lot')->name('show');
        Route::post('{lot}/fichier', 'deposer')->whereNumber('lot')->name('deposer');
        Route::put('{lot}/correspondance', 'mapping')->whereNumber('lot')->name('mapping');
        Route::post('{lot}/analyser', 'analyser')->whereNumber('lot')->name('analyser');
        Route::post('{lot}/soumettre', 'soumettre')->whereNumber('lot')->name('soumettre');
        Route::post('{lot}/decision', 'agir')->whereNumber('lot')->name('agir');
        Route::post('{lot}/executer', 'executer')->whereNumber('lot')->name('executer');
        Route::post('{lot}/annuler', 'annuler')->whereNumber('lot')->name('annuler');
        Route::get('{lot}/rapports/{type}.{format}', 'rapport')->whereNumber('lot')->whereIn('type', ['anomalies', 'operations'])->whereIn('format', ['csv', 'xlsx'])->name('rapport');
    });

    // Module « Mes Tâches » : file de travail personnelle (accessible à tout utilisateur authentifié ;
    // chacun n'y voit que les tâches qu'il peut traiter, les vues collectives restent contrôlées).
    Route::get('mes-taches', [\App\Http\Controllers\TacheController::class, 'index'])->name('taches.index');
    Route::get('mes-taches/supervision', [\App\Http\Controllers\TacheController::class, 'supervision'])->name('taches.supervision');
    Route::get('mes-taches/export', [\App\Http\Controllers\TacheController::class, 'export'])->name('taches.export');
    Route::get('mes-taches/{tache}', [\App\Http\Controllers\TacheController::class, 'show'])->whereNumber('tache')->name('taches.show');
    Route::post('mes-taches/{tache}/{action}', [\App\Http\Controllers\TacheController::class, 'agir'])->whereNumber('tache')
        ->whereIn('action', ['prendre_en_charge', 'liberer', 'suspendre', 'reprendre', 'commenter', 'prioriser', 'reaffecter', 'decider'])->name('taches.agir');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/apercu', [NotificationController::class, 'apercu'])->name('notifications.apercu');
    Route::post('notifications/{id}/lue', [NotificationController::class, 'marquerLue'])->name('notifications.lue')->whereUuid('id');
    Route::post('notifications/{id}/non-lue', [NotificationController::class, 'marquerNonLue'])->name('notifications.non-lue')->whereUuid('id');
    Route::get('notifications/{id}/ouvrir', [NotificationController::class, 'ouvrir'])->name('notifications.ouvrir')->whereUuid('id');
    Route::post('notifications/tout-lu', [NotificationController::class, 'toutMarquerLu'])->name('notifications.tout-lu');
    Route::post('notifications/{id}/archiver', [NotificationController::class, 'archiver'])->name('notifications.archiver')->whereUuid('id');

    Route::get('profil/notifications', [ProfilNotificationController::class, 'edit'])->name('profil.notifications.edit');
    Route::put('profil/notifications', [ProfilNotificationController::class, 'update'])->name('profil.notifications.update');

    Route::prefix('alertes')->name('alertes.')->group(function () {
        Route::get('/', [AlerteController::class, 'index'])->name('index');
        Route::post('{alerte}/resoudre', [AlerteController::class, 'resoudre'])->name('resoudre');
        Route::get('export/csv', [AlerteController::class, 'export'])->name('export');
    });

    Route::get('rbm', function () {
        $ex = \App\Models\Exercice::firstWhere('statut', 'ouvert') ?? \App\Models\Exercice::latest('annee')->first();
        abort_unless($ex, 404, 'Aucun exercice.');

        return redirect()->route('rbm.hub', ['exercice' => $ex->id]);
    })->name('rbm.home');

    // L1 — Planification stratégique institutionnelle (Phase P1)
    Route::prefix('strategie')->name('strategie.')->group(function () {
        Route::redirect('/', '/strategie/hub')->name('home');
        Route::get('hub', [StrategieModuleController::class, 'hub'])->name('hub');
        Route::get('aide', [StrategieAideController::class, 'index'])->name('aide');
        Route::get('aide/manuel.pdf', [StrategieAideController::class, 'pdf'])->name('aide.pdf');
        Route::get('aide/manuel.docx', [StrategieAideController::class, 'word'])->name('aide.word');
        Route::get('export/{catalogue}.xlsx', [StrategieExportController::class, 'excel'])->name('export.excel');
        Route::get('export/synthese.pdf', [StrategiePdfController::class, 'synthese'])->name('export.pdf');
        Route::get('export/matrice.pdf', [StrategiePdfController::class, 'matrice'])->name('export.matrice.pdf');
        Route::get('export/coherence.pdf', [StrategiePdfController::class, 'coherence'])->name('export.coherence.pdf');
        Route::get('export/{catalogue}/archiver.xlsx', [StrategieExportController::class, 'archiver'])->name('export.archiver.excel');
        Route::get('export/{rapport}/archiver.pdf', [StrategiePdfController::class, 'archiver'])->name('export.archiver.pdf');
        Route::get('export/pilotage.pdf', [StrategieDashboardController::class, 'pdf'])->name('export.pilotage.pdf');
        Route::get('dashboard/decisionnel', [StrategieDashboardController::class, 'decisionnel'])->name('dashboard.decisionnel');
        Route::get('pont-pap', [StrategiePontPapController::class, 'index'])->name('pont-pap');
        Route::post('pont-pap/synchroniser', [StrategiePontPapController::class, 'synchroniser'])->name('pont-pap.synchroniser');
        Route::post('pont-pap/{programmationAnnuelle}/lier', [StrategiePontPapController::class, 'lier'])->name('pont-pap.lier');
        Route::get('rapport/coherence', [StrategieRapportController::class, 'coherence'])->name('rapport.coherence');
        Route::post('documents/{type}/{id}', [StrategieDocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{piece}/download', [StrategieDocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{piece}', [StrategieDocumentController::class, 'destroy'])->name('documents.destroy');
        Route::post('plans/{plan}/soumettre', [StrategieValidationController::class, 'soumettre'])->name('plans.soumettre');
        Route::post('plans/{plan}/valider', [StrategieValidationController::class, 'valider'])->name('plans.valider');
        Route::post('plans/{plan}/rejeter', [StrategieValidationController::class, 'rejeter'])->name('plans.rejeter');
        Route::get('matrice', [StrategieMatriceController::class, 'index'])->name('matrice');
        Route::get('pont-rbm', [StrategiePontRbmController::class, 'index'])->name('pont-rbm');
        Route::put('pont-rbm/{pilier}', [StrategiePontRbmController::class, 'update'])->name('pont-rbm.update');
        Route::get('alignements', [AlignementStrategiqueController::class, 'index'])->name('alignements.index');
        Route::get('alignements/create', [AlignementStrategiqueController::class, 'create'])->name('alignements.create');
        Route::post('alignements', [AlignementStrategiqueController::class, 'store'])->name('alignements.store');
        Route::delete('alignements/{alignement}', [AlignementStrategiqueController::class, 'destroy'])->name('alignements.destroy');
        Route::post('{entite}/{id}/archiver', [StrategieArchiveController::class, 'archiver'])->name('archiver');
        Route::post('{entite}/{id}/restaurer', [StrategieArchiveController::class, 'restaurer'])->name('restaurer');

        Route::resource('visions', VisionController::class);
        Route::resource('plans', PlanStrategiqueController::class);
        Route::resource('orientations', OrientationStrategiqueController::class);
        Route::resource('programmes', ProgrammeStrategiqueController::class)
            ->parameters(['programmes' => 'programmeStrategique']);
        Route::resource('projets', ProjetStrategiqueController::class);
        Route::resource('portefeuilles', PortefeuilleProjetController::class);
        Route::resource('programmations-pluri', ProgrammationPluriannuelleController::class)
            ->parameters(['programmations-pluri' => 'programmationPluriannuelle']);
        Route::resource('programmations-annuelles', ProgrammationAnnuelleController::class)
            ->parameters(['programmations-annuelles' => 'programmationAnnuelle']);
        Route::resource('objectifs', ObjectifStrategiqueController::class)
            ->parameters(['objectifs' => 'objectifStrategique']);
        Route::resource('indicateurs', IndicateurStrategiqueController::class)
            ->parameters(['indicateurs' => 'indicateurStrategique']);
        Route::resource('risques', RisqueStrategiqueController::class)
            ->parameters(['risques' => 'risqueStrategique']);
    });

    // L1 — Plan Annuel de Performance (D3) + moteur de workflow (D14)
    Route::get('paps/export/liste', [PapExportController::class, 'liste'])->name('paps.export.liste');
    Route::get('paps/pilotage', [PapDashboardController::class, 'index'])->name('paps.dashboard');
    Route::post('paps/pilotage/import', [PapDashboardController::class, 'importer'])->name('paps.dashboard.import');
    Route::get('paps', [PapController::class, 'index'])->name('paps.index');
    Route::get('paps/create', [PapController::class, 'create'])->name('paps.create');
    Route::post('paps', [PapController::class, 'store'])->name('paps.store');
    Route::get('paps/{pap}/edit', [PapController::class, 'edit'])->name('paps.edit');
    Route::put('paps/{pap}', [PapController::class, 'update'])->name('paps.update');
    Route::delete('paps/{pap}', [PapController::class, 'destroy'])->name('paps.destroy');
    Route::post('paps/{pap}/risques', [PapGarController::class, 'storeRisque'])->name('paps.risques.store');
    Route::put('paps/{pap}/risques/{risque}', [PapGarController::class, 'updateRisque'])->name('paps.risques.update');
    Route::delete('paps/{pap}/risques/{risque}', [PapGarController::class, 'destroyRisque'])->name('paps.risques.destroy');
    Route::post('paps/{pap}/mesures-correctives', [PapGarController::class, 'storeMesure'])->name('paps.mesures.store');
    Route::put('paps/{pap}/mesures-correctives/{mesure}', [PapGarController::class, 'updateMesure'])->name('paps.mesures.update');
    Route::post('paps/{pap}/ecarts', [PapGarController::class, 'enregistrerEcarts'])->name('paps.ecarts.store');
    Route::get('paps/{pap}/historiques/{historique}/snapshot', [PapGarController::class, 'snapshot'])->name('paps.snapshot');
    Route::get('paps/{pap}/pdf', [PapController::class, 'pdf'])->name('paps.pdf');
    Route::get('paps/{pap}/execution-financiere', [PapIntegrationController::class, 'executionFinanciere'])->name('paps.execution-financiere');
    Route::post('paps/{pap}/alignements/synchroniser', [PapIntegrationController::class, 'synchroniserAlignements'])->name('paps.alignements.sync');
    Route::get('paps/{pap}', [PapController::class, 'show'])->name('paps.show');
    Route::post('paps/{pap}/soumettre', [PapController::class, 'soumettre'])->name('paps.soumettre');
    Route::post('paps/{pap}/revision', [PapController::class, 'demanderRevision'])->name('paps.revision');
    Route::post('paps/{pap}/agir', [PapController::class, 'agir'])->name('paps.agir');

    // L2 — Référentiel financier (hub + exports Phase D)
    Route::prefix('referentiel-financier')->name('referentiel.')->group(function () {
        Route::get('/', [ReferentielFinancierController::class, 'hub'])->name('hub');
        Route::get('export/sources', [ReferentielFinancierExportController::class, 'sourcesExcel'])->name('export.sources');
        Route::get('export/partenaires', [ReferentielFinancierExportController::class, 'partenairesExcel'])->name('export.partenaires');
        Route::get('export/conventions', [ReferentielFinancierExportController::class, 'conventionsExcel'])->name('export.conventions');
        Route::get('pdf/synthese', [ReferentielFinancierExportController::class, 'synthesePdf'])->name('pdf.synthese');
        Route::get('pdf/sources', [ReferentielFinancierExportController::class, 'sourcesPdf'])->name('pdf.sources');
        Route::get('pdf/ptf', [ReferentielFinancierExportController::class, 'ptfPdf'])->name('pdf.ptf');
        Route::get('export/{cle}/archiver', [ReferentielFinancierExportController::class, 'archiverExcel'])->name('export.archiver');
        Route::get('pdf/{cle}/archiver', [ReferentielFinancierExportController::class, 'archiverPdf'])->name('pdf.archiver');
    });

    // L2 — Gestion des Recettes (R) : prévisions + suivi des recettes
    Route::get('recettes', [RecetteController::class, 'index'])->name('recettes.index');
    Route::post('recettes/{recette}/ventiler', [RecetteController::class, 'ventiler'])->name('recettes.ventiler');
    Route::get('recettes/{recette}', [RecetteController::class, 'show'])->name('recettes.show');
    Route::post('previsions-recettes/{prevision}/reviser', [PrevisionRecetteController::class, 'reviser'])->name('previsions-recettes.reviser');
    Route::resource('previsions-recettes', PrevisionRecetteController::class)->parameters(['previsions-recettes' => 'prevision']);

    Route::post('encaissements/{encaissement}/justificatifs', [EncaissementController::class, 'ajouterJustificatif'])->name('encaissements.justificatifs.store');
    Route::post('encaissements/{encaissement}/soumettre', [EncaissementController::class, 'soumettre'])->name('encaissements.soumettre');
    Route::post('encaissements/{encaissement}/agir', [EncaissementController::class, 'agir'])->name('encaissements.agir');
    Route::post('encaissements/{encaissement}/valider', [EncaissementController::class, 'valider'])->name('encaissements.valider');
    Route::post('encaissements/{encaissement}/rapprocher', [EncaissementController::class, 'rapprocher'])->name('encaissements.rapprocher');
    Route::post('encaissements/{encaissement}/annuler', [EncaissementController::class, 'annuler'])->name('encaissements.annuler');
    Route::resource('encaissements', EncaissementController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::post('appels-fonds/{appelsFond}/recouvrer', [AppelFondsController::class, 'recouvrer'])->name('appels-fonds.recouvrer');
    Route::post('appels-fonds/{appelsFond}/relancer', [AppelFondsController::class, 'relancer'])->name('appels-fonds.relancer');
    Route::post('appels-fonds/{appelsFond}/annuler', [AppelFondsController::class, 'annuler'])->name('appels-fonds.annuler');
    Route::resource('appels-fonds', AppelFondsController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy'])
        ->parameters(['appels-fonds' => 'appelsFond']);

    // Plans d'apurement des arriérés (échéancier de règlement)
    Route::post('appels-fonds/{appelsFond}/plan-apurement', [PlanApurementController::class, 'store'])->name('plans-apurement.store');
    Route::get('plans-apurement/{planApurement}', [PlanApurementController::class, 'show'])->name('plans-apurement.show');
    Route::post('plans-apurement/{planApurement}/annuler', [PlanApurementController::class, 'annuler'])->name('plans-apurement.annuler');
    Route::post('echeances-apurement/{echeance}/regler', [PlanApurementController::class, 'reglerEcheance'])->name('echeances-apurement.regler');

    // États & rapports du module Recettes (PDF / Excel / CSV)
    Route::prefix('recettes-rapports')->name('recettes.rapports.')->group(function () {
        Route::get('/', [RecettesRapportController::class, 'index'])->name('index');
        Route::get('mobilisation.pdf', [RecettesRapportController::class, 'mobilisationPdf'])->name('mobilisation.pdf');
        Route::get('mobilisation.xlsx', [RecettesRapportController::class, 'mobilisationExcel'])->name('mobilisation.excel');
        Route::get('mobilisation.csv', [RecettesRapportController::class, 'mobilisationCsv'])->name('mobilisation.csv');
        Route::get('arrieres.pdf', [RecettesRapportController::class, 'arrieresPdf'])->name('arrieres.pdf');
        Route::get('arrieres.xlsx', [RecettesRapportController::class, 'arrieresExcel'])->name('arrieres.excel');
        Route::get('encaissements.xlsx', [RecettesRapportController::class, 'encaissementsExcel'])->name('encaissements.excel');
        Route::get('encaissements.csv', [RecettesRapportController::class, 'encaissementsCsv'])->name('encaissements.csv');
        Route::get('previsions.xlsx', [RecettesRapportController::class, 'previsionsExcel'])->name('previsions.excel');
        Route::get('previsions.csv', [RecettesRapportController::class, 'previsionsCsv'])->name('previsions.csv');
        Route::get('rapport-financier.pdf', [RecettesRapportController::class, 'rapportFinancierPdf'])->name('financier.pdf');
        Route::get('rapport-financier.docx', [RecettesRapportController::class, 'rapportFinancierWord'])->name('financier.word');
        Route::get('{cle}/archiver/{format}', [RecettesRapportController::class, 'archiver'])->name('archiver');
    });

    // L2 — Nomenclature budgétaire (D4) : CRUD hiérarchique Titre→Article→Paragraphe→Rubrique
    Route::get('nomenclature', [NomenclatureController::class, 'index'])->name('nomenclature.index');
    // Tableau de bord nomenclature (L3) — route littérale placée avant le wildcard {niveau}/{id}.
    Route::get('nomenclature/tableau-bord', [NomenclatureController::class, 'tableauBord'])->name('nomenclature.tableau');
    // Saisie assistée ligne par ligne (routes littérales placées avant le wildcard {niveau}/{id}).
    Route::get('nomenclature/assistant', [NomenclatureController::class, 'assistant'])->name('nomenclature.assistant');
    Route::get('nomenclature/assistant/contexte', [NomenclatureController::class, 'contexte'])->name('nomenclature.assistant.contexte');
    Route::get('nomenclature/assistant/verifier', [NomenclatureController::class, 'verifier'])->name('nomenclature.assistant.verifier');
    Route::get('nomenclature/{niveau}/create', [NomenclatureController::class, 'create'])->name('nomenclature.create');
    Route::post('nomenclature/{niveau}', [NomenclatureController::class, 'store'])->name('nomenclature.store');
    Route::get('nomenclature/{niveau}/{id}/edit', [NomenclatureController::class, 'edit'])->name('nomenclature.edit');
    Route::put('nomenclature/{niveau}/{id}', [NomenclatureController::class, 'update'])->name('nomenclature.update');
    Route::delete('nomenclature/{niveau}/{id}', [NomenclatureController::class, 'archiver'])->name('nomenclature.archiver');
    Route::post('nomenclature/{niveau}/{id}/restaurer', [NomenclatureController::class, 'restaurer'])->name('nomenclature.restaurer');
    Route::post('nomenclature/{niveau}/{id}/valider', [NomenclatureController::class, 'validerElement'])->name('nomenclature.valider');
    Route::get('nomenclature/{niveau}/{id}', [NomenclatureController::class, 'show'])->name('nomenclature.show');

    // L2 — Gestion budgétaire (D4) + imports/exports Excel (D6)
    Route::get('budget', [LigneBudgetaireController::class, 'index'])->name('budget.index');
    Route::prefix('budget/enveloppe')->name('budget.enveloppe.')->group(function () {
        Route::get('/', [BudgetEnveloppeController::class, 'index'])->name('index');
        Route::get('create', [BudgetEnveloppeController::class, 'create'])->name('create');
        Route::post('/', [BudgetEnveloppeController::class, 'store'])->name('store');
        Route::get('{budget}', [BudgetEnveloppeController::class, 'show'])->name('show');
        Route::get('{budget}/edit', [BudgetEnveloppeController::class, 'edit'])->name('edit');
        Route::put('{budget}', [BudgetEnveloppeController::class, 'update'])->name('update');
        Route::delete('{budget}', [BudgetEnveloppeController::class, 'destroy'])->name('destroy');
        Route::post('{budget}/soumettre', [BudgetEnveloppeController::class, 'soumettre'])->name('soumettre');
        Route::post('{budget}/revision', [BudgetEnveloppeController::class, 'demanderRevision'])->name('revision');
        Route::post('{budget}/agir', [BudgetEnveloppeController::class, 'agir'])->name('agir');
        Route::get('{budget}/preparations', [BudgetPreparationController::class, 'index'])->name('preparations');
        Route::post('{budget}/preparations/initialiser', [BudgetPreparationController::class, 'initialiser'])->name('preparations.initialiser');
        Route::put('{budget}/preparations/{preparation}', [BudgetPreparationController::class, 'update'])->name('preparations.update');
        Route::post('{budget}/preparations/soumettre', [BudgetPreparationController::class, 'soumettre'])->name('preparations.soumettre');
        Route::post('{budget}/preparations/valider', [BudgetPreparationController::class, 'valider'])->name('preparations.valider');
        Route::post('{budget}/preparations/arbitrer', [BudgetPreparationController::class, 'arbitrer'])->name('preparations.arbitrer');
        Route::post('{budget}/preparations/consolider', [BudgetPreparationController::class, 'consolider'])->name('preparations.consolider');
        Route::get('{budget}/simulations', [BudgetSimulationController::class, 'index'])->name('simulations');
        Route::post('{budget}/simulations', [BudgetSimulationController::class, 'store'])->name('simulations.store');
        Route::post('{budget}/simulations/comparer', [BudgetSimulationController::class, 'comparer'])->name('simulations.comparer');
        Route::post('{budget}/simulations/{simulation}/calculer', [BudgetSimulationController::class, 'calculer'])->name('simulations.calculer');
        Route::post('{budget}/simulations/{simulation}/retenir', [BudgetSimulationController::class, 'retenir'])->name('simulations.retenir');
        Route::get('{budget}/revisions', [BudgetRevisionController::class, 'index'])->name('revisions');
        Route::post('{budget}/revisions/rectificatif', [BudgetRevisionController::class, 'rectificatif'])->name('revisions.rectificatif');
        Route::post('{budget}/revisions/virement', [BudgetRevisionController::class, 'virement'])->name('revisions.virement');
        Route::post('{budget}/revisions/{revision}/valider', [BudgetRevisionController::class, 'valider'])->name('revisions.valider');
        Route::post('{budget}/revisions/lier-pluri', [BudgetRevisionController::class, 'lierProgrammation'])->name('revisions.lier-pluri');
        Route::get('{budget}/rapport', [BudgetRapportController::class, 'show'])->name('rapport');
    });
    Route::get('budget/rapport', [BudgetRapportController::class, 'exercice'])->name('budget.rapport');
    Route::get('budget/import', [BudgetImportController::class, 'form'])->name('budget.import.form');
    Route::post('budget/import/nomenclature', [BudgetImportController::class, 'importerNomenclature'])->name('budget.import.nomenclature');
    // Assistant d'import nomenclature (L4) : prévisualisation → confirmation.
    Route::post('budget/import/nomenclature/apercu', [BudgetImportController::class, 'previsualiserNomenclature'])->name('budget.import.nomenclature.apercu');
    Route::post('budget/import/nomenclature/confirmer', [BudgetImportController::class, 'confirmerNomenclature'])->name('budget.import.nomenclature.confirmer');
    Route::post('budget/import/nomenclature/annuler', [BudgetImportController::class, 'annulerApercuNomenclature'])->name('budget.import.nomenclature.annuler');
    Route::post('budget/import/lignes', [BudgetImportController::class, 'importerLignes'])->name('budget.import.lignes');
    Route::post('budget/import/pap', [BudgetImportController::class, 'importerPap'])->name('budget.import.pap');
    Route::post('budget/rapprochement-pap', [BudgetImportController::class, 'rapprocherPap'])->name('budget.rapprochement.executer');
    Route::get('budget/modele/nomenclature', [BudgetImportController::class, 'modeleNomenclature'])->name('budget.modele.nomenclature');
    Route::get('budget/modele/lignes', [BudgetImportController::class, 'modeleLignes'])->name('budget.modele.lignes');
    Route::get('budget/modele/pap', [BudgetImportController::class, 'modelePap'])->name('budget.modele.pap');
    Route::get('budget/export', [BudgetImportController::class, 'exporter'])->name('budget.export');
    Route::get('budget/create', [LigneBudgetaireController::class, 'create'])->name('budget.create');
    Route::post('budget', [LigneBudgetaireController::class, 'store'])->name('budget.store');
    Route::get('budget/{ligne}/edit', [LigneBudgetaireController::class, 'edit'])->name('budget.edit');
    Route::put('budget/{ligne}', [LigneBudgetaireController::class, 'update'])->name('budget.update');
    Route::delete('budget/{ligne}', [LigneBudgetaireController::class, 'destroy'])->name('budget.destroy');

    // Module « Structure du Budget » : restitution unifiée (arbre + dashboard consolidé) + assistant.
    Route::prefix('structure-budget')->name('structure-budget.')->group(function () {
        Route::get('/', [StructureBudgetController::class, 'arbre'])->name('arbre');
        Route::get('arbre-complet', [StructureBudgetController::class, 'arbreComplet'])->name('arbre-complet');
        Route::get('recherche', [StructureBudgetController::class, 'recherche'])->name('recherche');
        Route::get('import-export', [StructureBudgetImportExportController::class, 'hub'])->name('import-export');
        Route::get('export', [StructureBudgetImportExportController::class, 'export'])->name('export');
        Route::get('import/modele', [StructureBudgetImportExportController::class, 'modele'])->name('import.modele');
        Route::post('import/apercu', [StructureBudgetImportExportController::class, 'previsualiserImport'])->name('import.apercu');
        Route::post('import/confirmer', [StructureBudgetImportExportController::class, 'confirmerImport'])->name('import.confirmer');
        Route::post('import/annuler', [StructureBudgetImportExportController::class, 'annulerImport'])->name('import.annuler');
        Route::get('tableau-bord', [StructureBudgetController::class, 'tableauBord'])->name('tableau');
        Route::post('reconcilier', [StructureBudgetController::class, 'reconcilier'])->name('reconcilier');
        Route::get('journal', [StructureBudgetController::class, 'journal'])->name('journal');
        Route::get('assistant', [StructureBudgetController::class, 'assistant'])->name('assistant');
        Route::get('wizard', [StructureBudgetController::class, 'wizard'])->name('wizard');
        Route::post('wizard', [StructureBudgetController::class, 'wizardStore'])->name('wizard.store');
        Route::get('wizard/contexte', [StructureBudgetController::class, 'wizardContexte'])->name('wizard.contexte');
        Route::get('wizard/activites', [StructureBudgetController::class, 'wizardActivites'])->name('wizard.activites');
        Route::get('wizard/rubrique/{rubrique}', [StructureBudgetController::class, 'wizardRubrique'])->name('wizard.rubrique');
        Route::get('wizard/verifier', [StructureBudgetController::class, 'wizardVerifier'])->name('wizard.verifier');
        Route::get('assistant/activites', [StructureBudgetController::class, 'activitesDeStructure'])->name('assistant.activites');
        Route::post('lignes', [StructureBudgetController::class, 'creerLigne'])->name('ligne.store');
        Route::get('lignes/{ligne}', [StructureBudgetController::class, 'showLigne'])->name('ligne.show');
        Route::get('lignes/{ligne}/edit', [StructureBudgetController::class, 'editLigne'])->name('ligne.edit');
        Route::put('lignes/{ligne}', [StructureBudgetController::class, 'updateLigne'])->name('ligne.update');
        Route::delete('lignes/{ligne}', [StructureBudgetController::class, 'destroyLigne'])->name('ligne.destroy');
        Route::post('lignes/{ligne}/archive', [StructureBudgetController::class, 'archiveLigne'])->name('ligne.archive');
        Route::post('lignes/{ligne}/soumettre', [StructureBudgetController::class, 'soumettreLigne'])->name('ligne.soumettre');
        Route::post('lignes/{ligne}/valider', [StructureBudgetController::class, 'validerLigne'])->name('ligne.valider');
        Route::post('lignes/{ligne}/rejeter', [StructureBudgetController::class, 'rejeterLigne'])->name('ligne.rejeter');
        Route::get('structure/{structure}', [StructureBudgetController::class, 'structure'])->name('structure');
    });

    // P2 — Suivi Financier (hub pilotage budgétaire)
    Route::prefix('suivi-financier')->name('suivi-financier.')->group(function () {
        Route::get('/', [SuiviFinancierController::class, 'index'])->name('index');
        Route::get('anomalies', [SuiviFinancierController::class, 'anomalies'])->name('anomalies');
        Route::post('situation-capture', [SuiviFinancierController::class, 'capturerSituation'])->name('situation.capture');
    });

    // P0–P3 — Suivi-Évaluation (hub pilotage S&E)
    Route::prefix('suivi-evaluation')->name('suivi-evaluation.')->group(function () {
        Route::get('/', [SuiviEvaluationController::class, 'index'])->name('index');
        Route::post('generer', [SuiviEvaluationController::class, 'generer'])->name('generer');
        Route::post('generer-impact', [SuiviEvaluationController::class, 'genererImpact'])->name('generer-impact');
        Route::get('evaluations/{evaluation}', [SuiviEvaluationController::class, 'showEvaluation'])->name('evaluations.show');
        Route::resource('missions', MissionSuiviController::class)->only(['index', 'create', 'store', 'show']);
        Route::resource('plans', PlanAmeliorationController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('plans/{plan}/actions', [PlanAmeliorationController::class, 'storeAction'])->name('plans.actions.store');
    });

    // L3 — Chaîne de la dépense (D5) : Expression du besoin → Engagement
    Route::get('besoins/consolidation', [BesoinConsolidationController::class, 'index'])->name('besoins.consolidation');
    Route::post('besoins/consolidation', [BesoinConsolidationController::class, 'store'])->name('besoins.consolidation.store');
    Route::get('depense/export/besoins', [DepenseExportController::class, 'besoins'])->name('depense.export.besoins');
    Route::get('depense/export/engagements', [DepenseExportController::class, 'journalEngagements'])->name('depense.export.engagements');
    Route::get('depense/export/paiements', [DepenseExportController::class, 'journalPaiements'])->name('depense.export.paiements');
    Route::get('depense/export/restes-a-payer', [DepenseExportController::class, 'restesAPayer'])->name('depense.export.restes');
    Route::get('depense/export/restes-a-engager', [DepenseExportController::class, 'restesAEngager'])->name('depense.export.restes-engager');
    Route::get('depense/export/liquidations', [DepenseExportController::class, 'journalLiquidations'])->name('depense.export.liquidations');
    Route::get('depense/export/ordonnancements', [DepenseExportController::class, 'journalOrdonnancements'])->name('depense.export.ordonnancements');
    Route::get('depense/export/consolide', [DepenseExportController::class, 'journalConsolide'])->name('depense.export.consolide');
    Route::get('depense/export/ecritures', [DepenseExportController::class, 'ecrituresComptables'])->name('depense.export.ecritures');
    Route::get('depense/export/{cle}/archiver', [DepenseExportController::class, 'archiver'])->name('depense.export.archiver');
    Route::get('depense/dossier/besoin/{besoin}', [\App\Http\Controllers\Depense\DepenseDossierController::class, 'besoin'])->name('depense.dossier.besoin');
    Route::get('depense/dossier/engagement/{engagement}', [\App\Http\Controllers\Depense\DepenseDossierController::class, 'engagement'])->name('depense.dossier.engagement');
    Route::get('depense/verifier/{type}/{id}', [\App\Http\Controllers\Depense\DepenseDossierController::class, 'verifier'])
        ->whereIn('type', ['besoin', 'engagement', 'liquidation', 'ordonnancement', 'paiement'])
        ->name('depense.verifier');
    Route::get('depense/tracabilite', [\App\Http\Controllers\Depense\TracabiliteDepenseController::class, 'index'])->name('depense.tracabilite.index');
    Route::get('depense/tracabilite/{type}/{id}', [\App\Http\Controllers\Depense\TracabiliteDepenseController::class, 'show'])
        ->whereIn('type', ['besoin', 'engagement', 'liquidation', 'ordonnancement', 'paiement'])->whereNumber('id')->name('depense.tracabilite.show');
    Route::get('depense/rapprochement', [\App\Http\Controllers\Depense\RapprochementBancaireController::class, 'index'])->name('depense.rapprochement.index');
    Route::get('depense/rapprochement/create', [\App\Http\Controllers\Depense\RapprochementBancaireController::class, 'create'])->name('depense.rapprochement.create');
    Route::post('depense/rapprochement', [\App\Http\Controllers\Depense\RapprochementBancaireController::class, 'store'])->name('depense.rapprochement.store');
    Route::get('depense/rapprochement/{rapprochement}', [\App\Http\Controllers\Depense\RapprochementBancaireController::class, 'show'])->name('depense.rapprochement.show');
    Route::post('depense/rapprochement/{rapprochement}/importer', [\App\Http\Controllers\Depense\RapprochementBancaireController::class, 'importer'])->name('depense.rapprochement.importer');
    Route::post('depense/rapprochement/{rapprochement}/auto', [\App\Http\Controllers\Depense\RapprochementBancaireController::class, 'auto'])->name('depense.rapprochement.auto');
    Route::post('depense/rapprochement/{rapprochement}/lier', [\App\Http\Controllers\Depense\RapprochementBancaireController::class, 'lier'])->name('depense.rapprochement.lier');
    Route::post('depense/rapprochement/{rapprochement}/cloturer', [\App\Http\Controllers\Depense\RapprochementBancaireController::class, 'cloturer'])->name('depense.rapprochement.cloturer');

    Route::post('besoins/{besoin}/soumettre', [ExpressionBesoinController::class, 'soumettre'])->name('besoins.soumettre');
    Route::post('besoins/{besoin}/agir', [ExpressionBesoinController::class, 'agir'])->name('besoins.agir');
    Route::post('besoins/{besoin}/convertir', [ExpressionBesoinController::class, 'convertir'])->name('besoins.convertir');
    Route::get('besoins/{besoin}/pdf', [ExpressionBesoinController::class, 'pdf'])->name('besoins.pdf');
    Route::resource('besoins', ExpressionBesoinController::class)->parameters(['besoins' => 'besoin']);

    Route::post('engagements/{engagement}/soumettre', [EngagementController::class, 'soumettre'])->name('engagements.soumettre');
    Route::post('engagements/{engagement}/agir', [EngagementController::class, 'agir'])->name('engagements.agir');
    Route::post('engagements/{engagement}/annuler', [EngagementController::class, 'annuler'])->name('engagements.annuler');
    Route::post('engagements/{engagement}/degager', [EngagementController::class, 'degager'])->name('engagements.degager');
    Route::get('engagements/{engagement}/pdf', [EngagementController::class, 'pdf'])->name('engagements.pdf');
    Route::get('engagements/{engagement}/certificat', [EngagementController::class, 'certificat'])->name('engagements.certificat');
    Route::resource('engagements', EngagementController::class)->only(['index', 'create', 'store', 'show']);

    // L3 — Liquidation → Ordonnancement → Paiement (générique, D5)
    // Cockpit d'exécution de la dépense (route littérale AVANT le wildcard depense/{type}).
    Route::get('depense-cockpit', [CockpitDepenseController::class, 'index'])->name('depense.cockpit');
    Route::get('depense/{type}', [EtapeDepenseController::class, 'index'])->name('depense.index');
    Route::get('depense/{type}/create', [EtapeDepenseController::class, 'create'])->name('depense.create');
    Route::post('depense/{type}', [EtapeDepenseController::class, 'store'])->name('depense.store');
    Route::get('depense/{type}/{id}', [EtapeDepenseController::class, 'show'])->name('depense.show');
    Route::post('depense/{type}/{id}/soumettre', [EtapeDepenseController::class, 'soumettre'])->name('depense.soumettre');
    Route::post('depense/{type}/{id}/agir', [EtapeDepenseController::class, 'agir'])->name('depense.agir');
    Route::get('depense/{type}/{id}/pdf', [EtapeDepenseController::class, 'pdf'])->name('depense.pdf');

    // L3bis — Marchés publics / PPM (D5 étendu) + Contrats
    Route::resource('marches', MarcheController::class)->parameters(['marches' => 'marche']);
    Route::resource('contrats', ContratController::class);

    // L4bis — Suivi des recommandations (D7 étendu)
    Route::resource('recommandations', RecommandationController::class)->parameters(['recommandations' => 'recommandation']);

    // L4ter — Ateliers & formations
    Route::resource('formations', FormationController::class);

    // L4quater — Immobilisations & inventaire (P2-1)
    Route::resource('immobilisations', ImmobilisationController::class);

    // GED — pièces justificatives (D8) — socle existant conservé
    Route::post('pieces/{type}/{id}', [PieceController::class, 'store'])->name('pieces.store');
    Route::get('pieces/{piece}/download', [PieceController::class, 'download'])->name('pieces.download');
    Route::delete('pieces/{piece}', [PieceController::class, 'destroy'])->name('pieces.destroy');

    // GED — Module complet de gestion documentaire
    Route::prefix('ged')->name('ged.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Ged\GedDashboardController::class, 'index'])->name('dashboard');
        Route::get('recherche', [\App\Http\Controllers\Ged\GedDocumentController::class, 'rechercher'])->name('recherche');
        Route::get('archives', [\App\Http\Controllers\Ged\GedDocumentController::class, 'archives'])->name('archives');
        Route::get('partages', [\App\Http\Controllers\Ged\GedDocumentController::class, 'partages'])->name('partages');
        Route::get('documents/confidentiels', [\App\Http\Controllers\Ged\GedDocumentController::class, 'confidentiels'])->name('documents.confidentiels');
        Route::get('documents/expires', [\App\Http\Controllers\Ged\GedDocumentController::class, 'expires'])->name('documents.expires');
        Route::get('documents/{document}/download', [\App\Http\Controllers\Ged\GedDocumentController::class, 'download'])->name('documents.download');
        Route::get('documents/{document}/preview', [\App\Http\Controllers\Ged\GedDocumentController::class, 'preview'])->name('documents.preview');
        Route::post('documents/{document}/version', [\App\Http\Controllers\Ged\GedDocumentController::class, 'version'])->name('documents.version');
        Route::post('documents/{document}/archiver', [\App\Http\Controllers\Ged\GedDocumentController::class, 'archiver'])->name('documents.archiver');
        Route::post('documents/{document}/restaurer', [\App\Http\Controllers\Ged\GedDocumentController::class, 'restaurer'])->name('documents.restaurer');
        Route::resource('documents', \App\Http\Controllers\Ged\GedDocumentController::class)->parameters(['documents' => 'document']);
        Route::get('classement', [\App\Http\Controllers\Ged\GedAdminController::class, 'classement'])->name('classement');
        Route::get('categories', [\App\Http\Controllers\Ged\GedAdminController::class, 'categories'])->name('categories');
        Route::post('categories', [\App\Http\Controllers\Ged\GedAdminController::class, 'storeCategorie'])->name('categories.store');
        Route::get('types', [\App\Http\Controllers\Ged\GedAdminController::class, 'types'])->name('types');
        Route::post('types', [\App\Http\Controllers\Ged\GedAdminController::class, 'storeType'])->name('types.store');
        Route::get('dossiers', [\App\Http\Controllers\Ged\GedAdminController::class, 'dossiers'])->name('dossiers');
        Route::post('dossiers', [\App\Http\Controllers\Ged\GedAdminController::class, 'storeDossier'])->name('dossiers.store');
        Route::get('versions', [\App\Http\Controllers\Ged\GedAdminController::class, 'versions'])->name('versions');
        Route::get('signatures', [\App\Http\Controllers\Ged\GedAdminController::class, 'signatures'])->name('signatures.index');
        Route::post('signatures/demander', [\App\Http\Controllers\Ged\GedAdminController::class, 'demanderSignature'])->name('signatures.demander');
        Route::post('signatures/{signature}/signer', [\App\Http\Controllers\Ged\GedAdminController::class, 'signer'])->name('signatures.signer');
        Route::post('signatures/{signature}/rejeter', [\App\Http\Controllers\Ged\GedAdminController::class, 'rejeterSignature'])->name('signatures.rejeter');
        Route::get('workflows', [\App\Http\Controllers\Ged\GedAdminController::class, 'workflows'])->name('workflows');
        Route::get('validations', [\App\Http\Controllers\Ged\GedAdminController::class, 'validations'])->name('validations');
        Route::post('validations/{validation}/valider', [\App\Http\Controllers\Ged\GedAdminController::class, 'valider'])->name('validations.valider');
        Route::post('validations/{validation}/rejeter', [\App\Http\Controllers\Ged\GedAdminController::class, 'rejeterValidation'])->name('validations.rejeter');
        Route::get('conservation', [\App\Http\Controllers\Ged\GedAdminController::class, 'conservation'])->name('conservation');
        Route::get('journal', [\App\Http\Controllers\Ged\GedAdminController::class, 'journal'])->name('journal');
        Route::get('parametres', [\App\Http\Controllers\Ged\GedAdminController::class, 'parametres'])->name('parametres');

        // P3-3 — Wiki & procédures (base de connaissances)
        Route::post('connaissances/{connaissance}/publier', [\App\Http\Controllers\Ged\ConnaissanceController::class, 'publier'])->name('connaissances.publier');
        Route::post('connaissances/{connaissance}/archiver', [\App\Http\Controllers\Ged\ConnaissanceController::class, 'archiver'])->name('connaissances.archiver');
        Route::resource('connaissances', \App\Http\Controllers\Ged\ConnaissanceController::class)->parameters(['connaissances' => 'connaissance']);

        // Phase 2 — Exports & rattachements métier
        Route::post('attach/{type}/{id}', [\App\Http\Controllers\Ged\GedAttachmentController::class, 'store'])->name('attach.store');
        Route::post('documents/{document}/partager', [\App\Http\Controllers\Ged\GedAttachmentController::class, 'partager'])->name('documents.partager');
        Route::prefix('exports')->name('exports.')->group(function () {
            Route::get('documents/excel', [\App\Http\Controllers\Ged\GedExportController::class, 'registreDocuments'])->name('documents.excel');
            Route::get('archives/excel', [\App\Http\Controllers\Ged\GedExportController::class, 'registreArchives'])->name('archives.excel');
            Route::get('signatures/excel', [\App\Http\Controllers\Ged\GedExportController::class, 'registreSignatures'])->name('signatures.excel');
            Route::get('journal/excel', [\App\Http\Controllers\Ged\GedExportController::class, 'registreJournal'])->name('journal.excel');
            Route::get('registres/{registre}/archiver', [\App\Http\Controllers\Ged\GedExportController::class, 'archiver'])->name('registres.archiver');
            Route::get('documents/{document}/fiche', [\App\Http\Controllers\Ged\GedExportController::class, 'ficheDocument'])->name('documents.fiche');
            Route::get('documents/{document}/certificat-integrite', [\App\Http\Controllers\Ged\GedExportController::class, 'certificatIntegrite'])->name('documents.certificat_integrite');
            Route::get('documents/{document}/certificat-signature', [\App\Http\Controllers\Ged\GedExportController::class, 'certificatSignature'])->name('documents.certificat_signature');
            Route::get('documents/{document}/bordereau-depot', [\App\Http\Controllers\Ged\GedExportController::class, 'bordereauDepot'])->name('documents.bordereau_depot');
            Route::get('documents/{document}/bordereau-archivage', [\App\Http\Controllers\Ged\GedExportController::class, 'bordereauArchivage'])->name('documents.bordereau_archivage');
        });

        // Phase 3 — Intégrations & recherches sauvegardées
        Route::post('recherche/sauvegarder', [\App\Http\Controllers\Ged\GedIntegrationController::class, 'sauvegarderRecherche'])->name('recherche.sauvegarder');
        Route::delete('recherche/{recherche}', [\App\Http\Controllers\Ged\GedIntegrationController::class, 'supprimerRecherche'])->name('recherche.supprimer');
        Route::post('rapports/{rapport}/indexer', [\App\Http\Controllers\Ged\GedIntegrationController::class, 'indexerRapport'])->name('rapports.indexer');
    });

    // L4 — Suivi-évaluation (D7)
    Route::post('suivi/{suivi}/cloturer', [CampagneSuiviController::class, 'cloturer'])->name('suivi.cloturer');
    Route::post('suivi/{suivi}/activites/{activite}/collecte', [CollecteController::class, 'enregistrer'])->name('suivi.collecte.enregistrer');
    Route::post('collectes/{collecte}/verifier', [CollecteController::class, 'verifier'])->name('suivi.collecte.verifier');
    Route::post('collectes/{collecte}/valider', [CollecteController::class, 'valider'])->name('suivi.collecte.valider');
    Route::post('collectes/{collecte}/rejeter', [CollecteController::class, 'rejeter'])->name('suivi.collecte.rejeter');
    Route::get('suivi/{campagne}/export/collectes/{format}', [\App\Http\Controllers\Suivi\SuiviExportController::class, 'collectes'])
        ->whereIn('format', ['pdf', 'xlsx', 'csv'])
        ->name('suivi.export.collectes');
    Route::get('suivi/{campagne}/export/collectes/{format}/archiver', [\App\Http\Controllers\Suivi\SuiviExportController::class, 'archiver'])
        ->whereIn('format', ['pdf', 'xlsx', 'csv'])
        ->name('suivi.export.collectes.archiver');
    Route::resource('suivi', CampagneSuiviController::class)->parameters(['suivi' => 'suivi'])->except(['edit', 'update']);

    // L5 — Restitution : tableaux de bord décisionnels (D10) + rapports (D9)
    Route::get('tableaux-bord', [TableauBordController::class, 'index'])->name('tableaux.index');
    Route::get('tableaux-bord/tresorerie', [TableauBordController::class, 'tresorerie'])->name('tableaux.tresorerie');
    Route::get('rapports', [RapportController::class, 'index'])->name('rapports.index');
    Route::get('rapports/execution/pdf', [RapportController::class, 'executionBudgetairePdf'])->name('rapports.execution.pdf');
    Route::get('rapports/execution/excel', [RapportController::class, 'executionBudgetaireExcel'])->name('rapports.execution.excel');
    Route::get('rapports/pap/pdf', [RapportController::class, 'papPdf'])->name('rapports.pap.pdf');

    // L5 — Module Reporting & Analyse institutionnel (D9 étendu : multi-format, historisé, signé, QR)
    Route::prefix('reporting')->name('reporting.')->group(function () {
        Route::get('/', [ReportingController::class, 'index'])->name('index');
        Route::get('cockpit', [ReportingController::class, 'cockpit'])->name('cockpit');
        Route::prefix('cockpit/live')->name('cockpit.live.')->group(function () {
            Route::get('snapshot', [CockpitLiveController::class, 'snapshot'])->name('snapshot');
            Route::get('drilldown', [CockpitLiveController::class, 'drilldown'])->name('drilldown');
            Route::get('ping', [CockpitLiveController::class, 'ping'])->name('ping');
        });
        Route::get('cockpit/activites-retard/export/{format}', [ReportingController::class, 'activitesRetardExport'])
            ->whereIn('format', ['pdf', 'xlsx', 'csv'])->name('cockpit.retard.export');
        Route::get('cockpit/activites/{activite}', [ReportingController::class, 'activiteDetail'])->name('cockpit.activite');
        Route::get('historique', [ReportingController::class, 'historique'])->name('historique');
        Route::get('planification', [ReportingController::class, 'planification'])->name('planification');
        Route::get('techniques', [ReportingTechniqueController::class, 'index'])->name('techniques');
        Route::prefix('modeles')->name('modeles.')->group(function () {
            Route::get('/', [RapportPersonnaliseController::class, 'index'])->name('index');
            Route::get('create', [RapportPersonnaliseController::class, 'create'])->name('create');
            Route::post('/', [RapportPersonnaliseController::class, 'store'])->name('store');
            Route::get('{rapportPersonnalise}', [RapportPersonnaliseController::class, 'show'])->name('show');
            Route::get('{rapportPersonnalise}/edit', [RapportPersonnaliseController::class, 'edit'])->name('edit');
            Route::put('{rapportPersonnalise}', [RapportPersonnaliseController::class, 'update'])->name('update');
            Route::delete('{rapportPersonnalise}', [RapportPersonnaliseController::class, 'destroy'])->name('destroy');
            Route::get('{rapportPersonnalise}/generer', [RapportPersonnaliseController::class, 'generer'])->name('generer');
        });
        Route::get('rapports/{rapport}/telecharger', [ReportingController::class, 'telecharger'])->name('telecharger');
        Route::post('rapports/{rapport}/signer', [ReportingController::class, 'signer'])->name('signer');
        Route::post('rapports/{rapport}/valider', [ReportingController::class, 'valider'])->name('valider');
        Route::post('rapports/{rapport}/publier', [ReportingController::class, 'publier'])->name('publier');
        Route::get('{type}/generer', [ReportingController::class, 'generer'])->name('generer');
        Route::match(['get', 'post'], '{type}/generer-async', [ReportingController::class, 'genererAsync'])->name('generer-async');
        Route::get('{type}', [ReportingController::class, 'show'])->name('show');
    });

    // Audit (D11)
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    // Module Journalisation, Traçabilité et Horodatage (routes littérales avant {journal}).
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('tableau-de-bord', [AuditController::class, 'tableauDeBord'])->name('tableau-de-bord');
        Route::get('securite', [AuditController::class, 'securite'])->name('securite');
        Route::get('integrite', [AuditController::class, 'integrite'])->name('integrite');
        Route::post('integrite/verifier', [AuditController::class, 'verifierIntegrite'])->name('integrite.verifier');
        Route::post('integrite/sceller', [AuditController::class, 'sceller'])->name('integrite.sceller');
        Route::get('export/{format}', [AuditController::class, 'export'])->whereIn('format', ['csv', 'json'])->name('export');
        Route::get('dossiers/{type}/{id}', [AuditController::class, 'dossier'])->whereNumber('id')->name('dossier');
        Route::get('utilisateurs/{user}', [AuditController::class, 'utilisateur'])->whereNumber('user')->name('utilisateur');
        Route::get('{journal}', [AuditController::class, 'show'])->whereNumber('journal')->name('show');
    });

    // Module Audit, Contrôle Interne & Conformité — Lot D : cockpit de gouvernance.
    Route::get('gouvernance/cockpit', [GouvernanceController::class, 'cockpit'])->name('gouvernance.cockpit');

    // Module Administration Technique — Lot T1 : centre de supervision (santé de la plateforme).
    Route::get('administration-technique', [AdministrationTechniqueController::class, 'cockpit'])->name('administration-technique.cockpit');

    // P3-5 — Monitoring des files d'attente (alternative native à Horizon).
    Route::prefix('administration-technique/files-attente')->name('administration-technique.queues.')->group(function () {
        Route::get('/', [QueueMonitorController::class, 'index'])->name('index');
        Route::get('echoues/{uuid}', [QueueMonitorController::class, 'show'])->name('show');
        Route::post('echoues/relancer-tout', [QueueMonitorController::class, 'retryAll'])->name('retry-all');
        Route::post('echoues/purger', [QueueMonitorController::class, 'flush'])->name('flush');
        Route::post('echoues/{uuid}/relancer', [QueueMonitorController::class, 'retry'])->name('retry');
        Route::delete('echoues/{uuid}', [QueueMonitorController::class, 'forget'])->name('forget');
    });

    // Module Administration Technique — Lot T2 : gestion des incidents (ITIL).
    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [IncidentController::class, 'index'])->name('index');
        Route::get('create', [IncidentController::class, 'create'])->name('create');
        Route::post('/', [IncidentController::class, 'store'])->name('store');
        Route::get('{incident}', [IncidentController::class, 'show'])->name('show');
        Route::get('{incident}/edit', [IncidentController::class, 'edit'])->name('edit');
        Route::put('{incident}', [IncidentController::class, 'update'])->name('update');
        Route::post('{incident}/avancer', [IncidentController::class, 'avancer'])->name('avancer');
        Route::post('{incident}/cloturer', [IncidentController::class, 'cloturer'])->name('cloturer');
        Route::post('{incident}/rejeter', [IncidentController::class, 'rejeter'])->name('rejeter');
        Route::post('{incident}/actions', [IncidentController::class, 'storeAction'])->name('actions.store');
    });

    // Module Administration Technique — Lot T3 : sauvegardes & restauration (ISO 22301).
    Route::prefix('sauvegardes')->name('sauvegardes.')->group(function () {
        Route::get('/', [SauvegardeController::class, 'index'])->name('index');
        Route::post('executer', [SauvegardeController::class, 'executer'])->name('executer');
        Route::get('{backup}/telecharger', [SauvegardeController::class, 'telecharger'])->name('telecharger');
        Route::post('restaurations', [SauvegardeController::class, 'demanderRestauration'])->name('restaurations.demander');
        Route::post('restaurations/{restauration}/valider', [SauvegardeController::class, 'validerRestauration'])->name('restaurations.valider');
        Route::post('restaurations/{restauration}/rejeter', [SauvegardeController::class, 'rejeterRestauration'])->name('restaurations.rejeter');
        Route::post('restaurations/{restauration}/executee', [SauvegardeController::class, 'marquerExecutee'])->name('restaurations.executee');
    });

    // Module Audit, Contrôle Interne & Conformité — Lot B : missions d'audit (INTOSAI).
    Route::prefix('audit-missions')->name('audit-missions.')->group(function () {
        Route::get('/', [MissionAuditController::class, 'index'])->name('index');
        Route::get('create', [MissionAuditController::class, 'create'])->name('create');
        Route::post('/', [MissionAuditController::class, 'store'])->name('store');
        Route::get('{mission}', [MissionAuditController::class, 'show'])->name('show');
        Route::get('{mission}/edit', [MissionAuditController::class, 'edit'])->name('edit');
        Route::put('{mission}', [MissionAuditController::class, 'update'])->name('update');
        Route::post('{mission}/cloturer', [MissionAuditController::class, 'cloturer'])->name('cloturer');
        Route::post('{mission}/constats', [MissionAuditController::class, 'storeConstat'])->name('constats.store');
        Route::put('{mission}/constats/{constat}', [MissionAuditController::class, 'updateConstat'])->name('constats.update');
    });

    // Module Audit, Contrôle Interne & Conformité — Lot C : COSO + non-conformités.
    Route::prefix('controle-interne')->name('controle-interne.')->group(function () {
        Route::get('/', [ControleInterneController::class, 'index'])->name('index');
        Route::get('create', [ControleInterneController::class, 'create'])->name('create');
        Route::post('/', [ControleInterneController::class, 'store'])->name('store');
        Route::get('{controle}/edit', [ControleInterneController::class, 'edit'])->name('edit');
        Route::put('{controle}', [ControleInterneController::class, 'update'])->name('update');
        Route::post('{controle}/evaluer', [ControleInterneController::class, 'evaluer'])->name('evaluer');
    });
    Route::prefix('conformite')->name('conformite.')->group(function () {
        Route::get('/', [NonConformiteController::class, 'index'])->name('index');
        Route::get('create', [NonConformiteController::class, 'create'])->name('create');
        Route::post('/', [NonConformiteController::class, 'store'])->name('store');
        Route::get('{nonConformite}', [NonConformiteController::class, 'show'])->name('show');
        Route::get('{nonConformite}/edit', [NonConformiteController::class, 'edit'])->name('edit');
        Route::put('{nonConformite}', [NonConformiteController::class, 'update'])->name('update');
        Route::post('{nonConformite}/suivi', [NonConformiteController::class, 'suivi'])->name('suivi');
    });

    // Module Audit, Contrôle Interne & Conformité — Lot A : registre & cartographie des risques.
    // Routes littérales (cartographie/create) avant le wildcard {risque}.
    Route::get('risques', [RisqueController::class, 'index'])->name('risques.index');
    Route::get('risques/cartographie', [RisqueController::class, 'cartographie'])->name('risques.cartographie');
    Route::get('risques/export/{format}', [RisqueController::class, 'export'])->name('risques.export');
    Route::get('risques/create', [RisqueController::class, 'create'])->name('risques.create');
    Route::post('risques', [RisqueController::class, 'store'])->name('risques.store');
    Route::get('risques/{risque}/edit', [RisqueController::class, 'edit'])->name('risques.edit');
    Route::put('risques/{risque}', [RisqueController::class, 'update'])->name('risques.update');
    Route::delete('risques/{risque}', [RisqueController::class, 'archiver'])->name('risques.archiver');
    Route::get('risques/{risque}', [RisqueController::class, 'show'])->name('risques.show');

    // Workflow institutionnel D14 (P1)
    Route::prefix('admin/workflows')->name('admin.workflows.')->group(function () {
        Route::get('/', [WorkflowAdminController::class, 'index'])->name('index');
        Route::get('create', [WorkflowAdminController::class, 'create'])->name('create');
        Route::post('/', [WorkflowAdminController::class, 'store'])->name('store');
        Route::get('{workflow}/edit', [WorkflowAdminController::class, 'edit'])->name('edit');
        Route::put('{workflow}', [WorkflowAdminController::class, 'update'])->name('update');
        Route::post('{workflow}/toggle', [WorkflowAdminController::class, 'toggle'])->name('toggle');
    });
    Route::prefix('admin/notifications/templates')->name('admin.notifications.templates.')->group(function () {
        Route::get('/', [AdminEmailTemplateController::class, 'index'])->name('index');
        Route::get('{template}/edit', [AdminEmailTemplateController::class, 'edit'])->name('edit');
        Route::put('{template}', [AdminEmailTemplateController::class, 'update'])->name('update');
    });

    // Interopérabilité & API (I-P1 → I-P3)
    Route::get('api/docs', [ApiDocsController::class, 'index'])->name('api.docs');
    Route::get('api/openapi/v1.json', [ApiDocsController::class, 'spec'])->name('api.openapi.v1');

    Route::prefix('admin/interoperabilite')->name('admin.interoperabilite.')->group(function () {
        Route::get('/', [InteropHubController::class, 'index'])->name('index');
        Route::get('journal', [InteropJournalController::class, 'index'])->name('journal');
        Route::get('monitoring', [InteropMonitoringController::class, 'index'])->name('monitoring');
        Route::get('exports/inventaire/{format}', [InteropExportController::class, 'inventaire'])
            ->where('format', 'csv|xlsx')->name('exports.inventaire');
        Route::get('exports/securite/{format}', [InteropExportController::class, 'rapportSecurite'])
            ->where('format', 'csv|pdf')->name('exports.securite');
        Route::prefix('apps')->name('apps.')->group(function () {
            Route::get('/', [InteropAppController::class, 'index'])->name('index');
            Route::get('create', [InteropAppController::class, 'create'])->name('create');
            Route::post('/', [InteropAppController::class, 'store'])->name('store');
            Route::get('{app}', [InteropAppController::class, 'show'])->name('show');
            Route::get('{app}/edit', [InteropAppController::class, 'edit'])->name('edit');
            Route::put('{app}', [InteropAppController::class, 'update'])->name('update');
            Route::post('{app}/suspendre', [InteropAppController::class, 'suspendre'])->name('suspendre');
            Route::post('{app}/reactiver', [InteropAppController::class, 'reactiver'])->name('reactiver');
            Route::post('{app}/revoquer-jetons', [InteropAppController::class, 'revoquerJetons'])->name('revoquer-jetons');
            Route::post('{app}/clients', [InteropClientController::class, 'store'])->name('clients.store');
            Route::post('{app}/clients/{client}/revoquer', [InteropClientController::class, 'revoquer'])->name('clients.revoquer');
        });
        Route::prefix('webhooks')->name('webhooks.')->group(function () {
            Route::get('/', [InteropWebhookController::class, 'index'])->name('index');
            Route::get('create', [InteropWebhookController::class, 'create'])->name('create');
            Route::post('/', [InteropWebhookController::class, 'store'])->name('store');
            Route::get('{webhook}', [InteropWebhookController::class, 'show'])->name('show');
            Route::get('{webhook}/edit', [InteropWebhookController::class, 'edit'])->name('edit');
            Route::put('{webhook}', [InteropWebhookController::class, 'update'])->name('update');
            Route::delete('{webhook}', [InteropWebhookController::class, 'destroy'])->name('destroy');
            Route::post('{webhook}/tester', [InteropWebhookController::class, 'tester'])->name('tester');
        });
    });

    Route::get('workflow/validations', [WorkflowCorbeilleController::class, 'index'])->name('workflow.validations');
    Route::get('workflow/audit', [WorkflowAuditController::class, 'index'])->name('workflow.audit');
    Route::get('workflow/dashboard', [WorkflowDashboardController::class, 'index'])->name('workflow.dashboard');
    Route::get('workflow/dashboard/export', [WorkflowDashboardController::class, 'export'])->name('workflow.dashboard.export');
});

// Vérification publique d'authenticité d'un rapport (QR code, sans authentification)
Route::get('verifier/rapport/{token}', [VerificationController::class, 'show'])->name('verification.rapport');

// Endpoint de santé technique (sondes externes / load-balancer) — public, sans détail sensible.
Route::get('health', [AdministrationTechniqueController::class, 'health'])->name('sante.health');

// Rapports CSP (Report-Only) — public, sans CSRF (navigateur).
Route::post('csp-report', [\App\Http\Controllers\CspReportController::class, 'store'])->name('csp.report');

// Vérification publique d'intégrité / signature document GED
Route::get('verifier/ged/{uuid}', [\App\Http\Controllers\Ged\GedAdminController::class, 'verifier'])->name('verification.ged')->whereUuid('uuid');
