<?php

use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\BesoinApiController;
use App\Http\Controllers\Api\V1\BesoinConsolidationApiController;
use App\Http\Controllers\Api\V1\DepenseDossierApiController;
use App\Http\Controllers\Api\V1\BudgetApiController;
use App\Http\Controllers\Api\V1\CockpitDepenseApiController;
use App\Http\Controllers\Api\V1\DepenseExportApiController;
use App\Http\Controllers\Api\V1\BudgetPreparationApiController;
use App\Http\Controllers\Api\V1\BudgetCreditsApiController;
use App\Http\Controllers\Api\V1\BudgetRapportApiController;
use App\Http\Controllers\Api\V1\BudgetRevisionApiController;
use App\Http\Controllers\Api\V1\BudgetSimulationApiController;
use App\Http\Controllers\Api\V1\ExerciceApiController;
use App\Http\Controllers\Api\V1\EngagementApiController;
use App\Http\Controllers\Api\V1\DonneesApiController;
use App\Http\Controllers\Api\V1\EtapeDepenseApiController;
use App\Http\Controllers\Api\V1\ConnaissanceApiController;
use App\Http\Controllers\Api\V1\GedApiController;
use App\Http\Controllers\Api\V1\GedReferentielApiController;
use App\Http\Controllers\Api\V1\NavigationApiController;
use App\Http\Controllers\Api\V1\PapApiController;
use App\Http\Controllers\Api\V1\PieceApiController;
use App\Http\Controllers\Api\V1\VerificationApiController;
use App\Http\Controllers\Api\V1\RapprochementApiController;
use App\Http\Controllers\Api\V1\RbmApiController;
use App\Http\Controllers\Api\V1\RbmNoeudApiController;
use App\Http\Controllers\Api\V1\RbmVueApiController;
use App\Http\Controllers\Api\V1\AppelFondsApiController;
use App\Http\Controllers\Api\V1\ContratApiController;
use App\Http\Controllers\Api\V1\MarcheApiController;
use App\Http\Controllers\Api\V1\PlanApurementApiController;
use App\Http\Controllers\Api\V1\RecettesApiController;
use App\Http\Controllers\Api\V1\RecettesEtatsApiController;
use App\Http\Controllers\Api\V1\NotificationsApiController;
use App\Http\Controllers\Api\V1\RapportPersonnaliseApiController;
use App\Http\Controllers\Api\V1\ReportingApiController;
use App\Http\Controllers\Api\V1\SessionAuthController;
use App\Http\Controllers\Api\V1\StructureApiController;
use App\Http\Controllers\Api\V1\StructureBudgetApiController;
use App\Http\Controllers\Api\V1\StructureBudgetImportApiController;
use App\Http\Controllers\Api\V1\CampagneSuiviApiController;
use App\Http\Controllers\Api\V1\FormationApiController;
use App\Http\Controllers\Api\V1\ImmobilisationApiController;
use App\Http\Controllers\Api\V1\InteropApiController;
use App\Http\Controllers\Api\V1\RecommandationApiController;
use App\Http\Controllers\Api\V1\SuiviApiController;
use App\Http\Controllers\Api\V1\TableauBordApiController;
use App\Http\Controllers\Api\V1\TomproController;
use App\Http\Controllers\Api\V1\UtilisateurApiController;
use App\Http\Controllers\Api\V1\WorkflowAdminApiController;
use App\Http\Controllers\Api\V1\WorkflowApiController;
use Illuminate\Support\Facades\Route;

/*
| API REST versionnée (D12). Journalisation api.log + rate limiting (I-P0).
| Authentification par jeton Sanctum + scopes (ability).
*/
Route::prefix('v1')->group(function () {

    Route::post('token', [AuthApiController::class, 'token'])
        ->middleware(['api.log', 'throttle:api-token'])
        ->name('api.token');

    Route::post('session/login', [SessionAuthController::class, 'login'])
        ->middleware(['api.log', 'throttle:spa-login'])
        ->name('api.session.login');
    Route::post('session/mfa', [SessionAuthController::class, 'mfa'])
        ->middleware(['api.log', 'throttle:spa-login'])
        ->name('api.session.mfa');

    Route::middleware(['auth:sanctum', 'api.connected', 'api.log', 'throttle:api'])->group(function () {
        Route::get('me', [AuthApiController::class, 'me'])->name('api.me');
        Route::post('logout', [AuthApiController::class, 'logout'])->name('api.logout');
        Route::post('session/logout', [SessionAuthController::class, 'logout'])->name('api.session.logout');
        Route::get('session/mfa/setup', [SessionAuthController::class, 'mfaSetup'])->name('api.session.mfa.setup');
        Route::post('session/mfa/setup', [SessionAuthController::class, 'mfaSetupConfirm'])->name('api.session.mfa.setup.confirm');
        Route::delete('session/mfa/setup', [SessionAuthController::class, 'mfaDisable'])->name('api.session.mfa.disable');

        Route::middleware('ability.or.session:lecture')->group(function () {
            Route::get('exercices', [DonneesApiController::class, 'exercices'])->name('api.exercices');
            Route::get('exercices/hub', [ExerciceApiController::class, 'index'])->name('api.exercices.hub');
            Route::get('exercices/{exercice}', [ExerciceApiController::class, 'show'])->name('api.exercices.show');
            Route::get('structures', [DonneesApiController::class, 'structures'])->name('api.structures');
            Route::get('structures/hub', [StructureApiController::class, 'index'])->name('api.structures.hub');
            Route::get('structures/options', [StructureApiController::class, 'options'])->name('api.structures.options');
            Route::get('structures/{structure}', [StructureApiController::class, 'show'])->name('api.structures.show');
            Route::get('institution', [DonneesApiController::class, 'institution'])->name('api.institution');
            Route::get('institution/gouvernance', [DonneesApiController::class, 'gouvernance'])->name('api.institution.gouvernance');
            Route::get('lignes-budgetaires', [DonneesApiController::class, 'lignesBudgetaires'])->name('api.lignes');
            Route::get('structure-budget/synthese', [StructureBudgetApiController::class, 'synthese'])->name('api.structure-budget.synthese');
            Route::get('structure-budget/arbre', [StructureBudgetApiController::class, 'arbre'])->name('api.structure-budget.arbre');
            Route::get('structure-budget/import-export', [StructureBudgetImportApiController::class, 'hub'])->name('api.structure-budget.import-export');
            Route::get('structure-budget/lignes', [StructureBudgetApiController::class, 'lignes'])->name('api.structure-budget.lignes');
            Route::get('structure-budget/lignes/{ligne}', [StructureBudgetApiController::class, 'showLigne'])->name('api.structure-budget.ligne');
            Route::get('besoins', [BesoinApiController::class, 'index'])->name('api.besoins.index');
            Route::get('besoins/options', [BesoinApiController::class, 'options'])->name('api.besoins.options');
            Route::get('besoins/consolidation', [BesoinConsolidationApiController::class, 'show'])->name('api.besoins.consolidation');
            Route::get('dossiers/besoin/{besoin}', [DepenseDossierApiController::class, 'besoin'])->name('api.dossiers.besoin');
            Route::get('dossiers/engagement/{engagement}', [DepenseDossierApiController::class, 'engagement'])->name('api.dossiers.engagement');
            Route::get('besoins/{besoin}', [BesoinApiController::class, 'show'])->name('api.besoins.show');
            Route::get('besoins/{besoin}/pdf', [BesoinApiController::class, 'pdf'])->name('api.besoins.pdf');
            Route::get('rapprochements', [RapprochementApiController::class, 'index'])->name('api.rapprochements.index');
            Route::get('rapprochements/{rapprochement}', [RapprochementApiController::class, 'show'])->name('api.rapprochements.show');
            Route::get('engagements', [EngagementApiController::class, 'index'])->name('api.engagements');
            Route::get('engagements/{engagement}', [EngagementApiController::class, 'show'])->name('api.engagements.show');
            Route::get('engagements/{engagement}/pdf', [EngagementApiController::class, 'pdf'])->name('api.engagements.pdf');
            Route::get('engagements/{engagement}/certificat', [EngagementApiController::class, 'certificat'])->name('api.engagements.certificat');
            Route::get('depense/{type}', [EtapeDepenseApiController::class, 'index'])->whereIn('type', ['liquidation', 'ordonnancement', 'paiement'])->name('api.depense.index');
            Route::get('depense/{type}/{id}', [EtapeDepenseApiController::class, 'show'])->whereIn('type', ['liquidation', 'ordonnancement', 'paiement'])->name('api.depense.show');
            Route::get('depense/{type}/{id}/pdf', [EtapeDepenseApiController::class, 'pdf'])->whereIn('type', ['liquidation', 'ordonnancement', 'paiement'])->name('api.depense.pdf');
            Route::get('pieces/{type}/{id}', [PieceApiController::class, 'index'])->whereIn('type', ['besoin', 'engagement', 'liquidation', 'ordonnancement', 'paiement'])->name('api.pieces.index');
            Route::get('pieces/{piece}/fichier', [PieceApiController::class, 'fichier'])->name('api.pieces.fichier');
            Route::get('verification/{type}/{id}', [VerificationApiController::class, 'show'])->whereIn('type', ['besoin', 'engagement', 'liquidation', 'ordonnancement', 'paiement'])->name('api.verification.show');
            Route::get('rbm/piliers', [RbmApiController::class, 'piliers'])->name('api.rbm.piliers');
            Route::get('rbm/matrice', [RbmApiController::class, 'matrice'])->name('api.rbm.matrice');
            Route::get('rbm/indicateurs', [RbmApiController::class, 'indicateurs'])->name('api.rbm.indicateurs');
            Route::get('rbm/hub', [RbmVueApiController::class, 'hub'])->name('api.rbm.hub');
            Route::get('rbm/vue', [RbmVueApiController::class, 'vue'])->name('api.rbm.vue');
            Route::get('rbm/niveaux/{niveau}/options', [RbmNoeudApiController::class, 'options'])->whereIn('niveau', ['pilier', 'axe', 'produit', 'sous_produit', 'activite', 'tache'])->name('api.rbm.niveaux.options');
            Route::get('rbm/niveaux/{niveau}/{id}', [RbmNoeudApiController::class, 'show'])->whereIn('niveau', ['pilier', 'axe', 'produit', 'sous_produit', 'activite', 'tache'])->name('api.rbm.niveaux.show');
            Route::get('budgets', [BudgetApiController::class, 'index'])->name('api.budgets.index');
            Route::get('budgets/options', [BudgetApiController::class, 'options'])->name('api.budgets.options');
            Route::get('budgets/{budget}', [BudgetApiController::class, 'show'])->name('api.budgets.show');
            Route::get('budgets/{budget}/synthese', [BudgetApiController::class, 'synthese'])->name('api.budgets.synthese');
            Route::get('budgets/{budget}/preparations', [BudgetPreparationApiController::class, 'show'])->name('api.budgets.preparations');
            Route::get('budgets/{budget}/simulations', [BudgetSimulationApiController::class, 'show'])->name('api.budgets.simulations');
            Route::get('budgets/{budget}/revisions', [BudgetRevisionApiController::class, 'show'])->name('api.budgets.revisions');
            Route::get('budgets/{budget}/rapport', [BudgetRapportApiController::class, 'show'])->name('api.budgets.rapport');
            Route::get('budget/credits/options', [BudgetCreditsApiController::class, 'options'])->name('api.budget.credits.options');
            Route::get('budget/credits', [BudgetCreditsApiController::class, 'index'])->name('api.budget.credits.index');
            Route::get('budget/credits/{ligne}', [BudgetCreditsApiController::class, 'show'])->name('api.budget.credits.show');
            Route::get('paps', [PapApiController::class, 'index'])->name('api.paps.index');
            Route::get('paps/options', [PapApiController::class, 'options'])->name('api.paps.options');
            Route::get('paps/pilotage', [PapApiController::class, 'pilotage'])->name('api.paps.pilotage');
            Route::get('paps/{pap}', [PapApiController::class, 'show'])->name('api.paps.show');
            Route::get('recettes', [RecettesApiController::class, 'recettes'])->name('api.recettes.index');
            Route::get('recettes/etats', [RecettesEtatsApiController::class, 'show'])->name('api.recettes.etats');
            Route::get('recettes/encaissements', [RecettesApiController::class, 'encaissements'])->name('api.recettes.encaissements');
            Route::get('recettes/encaissements/options', [RecettesApiController::class, 'optionsEncaissement'])->name('api.recettes.encaissements.options');
            Route::get('recettes/encaissements/{encaissement}', [RecettesApiController::class, 'showEncaissement'])->name('api.recettes.encaissements.show');
            Route::get('recettes/{recette}', [RecettesApiController::class, 'showRecette'])->name('api.recettes.show');
            Route::get('campagnes-suivi', [CampagneSuiviApiController::class, 'index'])->name('api.campagnes-suivi.index');
            Route::get('campagnes-suivi/{campagne}', [CampagneSuiviApiController::class, 'show'])->name('api.campagnes-suivi.show');
            Route::get('recommandations', [RecommandationApiController::class, 'index'])->name('api.recommandations.index');
            Route::get('recommandations/options', [RecommandationApiController::class, 'options'])->name('api.recommandations.options');
            Route::get('recommandations/{recommandation}', [RecommandationApiController::class, 'show'])->name('api.recommandations.show');
            Route::get('formations', [FormationApiController::class, 'index'])->name('api.formations.index');
            Route::get('formations/options', [FormationApiController::class, 'options'])->name('api.formations.options');
            Route::get('formations/{formation}', [FormationApiController::class, 'show'])->name('api.formations.show');
            Route::get('immobilisations', [ImmobilisationApiController::class, 'index'])->name('api.immobilisations.index');
            Route::get('immobilisations/options', [ImmobilisationApiController::class, 'options'])->name('api.immobilisations.options');
            Route::get('immobilisations/{immobilisation}', [ImmobilisationApiController::class, 'show'])->name('api.immobilisations.show');
            Route::get('marches', [MarcheApiController::class, 'index'])->name('api.marches.index');
            Route::get('marches/options', [MarcheApiController::class, 'options'])->name('api.marches.options');
            Route::get('marches/{marche}', [MarcheApiController::class, 'show'])->name('api.marches.show');
            Route::get('contrats', [ContratApiController::class, 'index'])->name('api.contrats.index');
            Route::get('contrats/options', [ContratApiController::class, 'options'])->name('api.contrats.options');
            Route::get('contrats/{contrat}', [ContratApiController::class, 'show'])->name('api.contrats.show');
            Route::get('appels-fonds', [AppelFondsApiController::class, 'index'])->name('api.appels-fonds.index');
            Route::get('appels-fonds/options', [AppelFondsApiController::class, 'options'])->name('api.appels-fonds.options');
            Route::get('appels-fonds/{appel}', [AppelFondsApiController::class, 'show'])->name('api.appels-fonds.show');
            Route::get('plans-apurement/{plan}', [PlanApurementApiController::class, 'show'])->name('api.plans-apurement.show');
            Route::get('ged/hub', [GedApiController::class, 'hub'])->name('api.ged.hub');
            Route::get('ged/recherche', [GedApiController::class, 'rechercher'])->name('api.ged.recherche');
            Route::get('ged/documents/options', [GedApiController::class, 'options'])->name('api.ged.documents.options');
            Route::get('ged/documents', [GedApiController::class, 'index'])->name('api.ged.documents.index');
            Route::get('ged/documents/{document:uuid}', [GedApiController::class, 'show'])->name('api.ged.documents.show');
            Route::get('ged/signatures', [GedApiController::class, 'signatures'])->name('api.ged.signatures.index');
            Route::get('ged/connaissances/options', [ConnaissanceApiController::class, 'options'])->name('api.ged.connaissances.options');
            Route::get('ged/connaissances', [ConnaissanceApiController::class, 'index'])->name('api.ged.connaissances.index');
            Route::get('ged/connaissances/{connaissance}', [ConnaissanceApiController::class, 'show'])->name('api.ged.connaissances.show');
            Route::get('ged/vues/{vue}', [GedReferentielApiController::class, 'vue'])->whereIn('vue', ['archives', 'partages', 'confidentiels', 'expires'])->name('api.ged.vues');
            Route::get('ged/classement', [GedReferentielApiController::class, 'classement'])->name('api.ged.classement');
            Route::get('ged/categories', [GedReferentielApiController::class, 'categories'])->name('api.ged.categories');
            Route::get('ged/types', [GedReferentielApiController::class, 'types'])->name('api.ged.types');
            Route::get('ged/dossiers', [GedReferentielApiController::class, 'dossiers'])->name('api.ged.dossiers');
            Route::get('ged/versions', [GedReferentielApiController::class, 'versions'])->name('api.ged.versions');
            Route::get('ged/workflows', [GedReferentielApiController::class, 'workflows'])->name('api.ged.workflows');
            Route::get('ged/conservation', [GedReferentielApiController::class, 'conservation'])->name('api.ged.conservation');
            Route::get('ged/journal', [GedReferentielApiController::class, 'journal'])->name('api.ged.journal');
            Route::get('ged/parametres', [GedReferentielApiController::class, 'parametres'])->name('api.ged.parametres');
            Route::get('tableau-bord', [TableauBordApiController::class, 'show'])->name('api.tableau-bord');
            Route::get('depense-cockpit', [CockpitDepenseApiController::class, 'show'])->name('api.depense.cockpit');
            Route::get('depense-exports', [DepenseExportApiController::class, 'index'])->name('api.depense.exports.index');
            Route::get('depense-exports/{cle}', [DepenseExportApiController::class, 'telecharger'])
                ->where('cle', 'besoins|engagements|liquidations|ordonnancements|paiements|restes-a-payer|restes-a-engager|consolide|ecritures')
                ->name('api.depense.exports.telecharger');
            Route::get('navigation', [NavigationApiController::class, 'show'])->name('api.navigation');
            Route::get('utilisateurs/hub', [UtilisateurApiController::class, 'index'])->name('api.utilisateurs.hub');
            Route::get('utilisateurs/options', [UtilisateurApiController::class, 'options'])->name('api.utilisateurs.options');
            Route::get('utilisateurs/{utilisateur}', [UtilisateurApiController::class, 'show'])->name('api.utilisateurs.show');
            Route::get('utilisateurs/{utilisateur}/perimetres', [UtilisateurApiController::class, 'perimetres'])->name('api.utilisateurs.perimetres');
        });

        Route::middleware('ability.or.session:lecture')->group(function () {
            Route::post('structure-budget/import/apercu', [StructureBudgetImportApiController::class, 'apercu'])->name('api.structure-budget.import.apercu');
            Route::post('structure-budget/import/confirmer', [StructureBudgetImportApiController::class, 'confirmer'])->name('api.structure-budget.import.confirmer');
            Route::post('structure-budget/import/annuler', [StructureBudgetImportApiController::class, 'annuler'])->name('api.structure-budget.import.annuler');
            Route::post('structure-budget/lignes/{ligne}/soumettre', [StructureBudgetApiController::class, 'soumettre'])->name('api.structure-budget.soumettre');
            Route::post('structure-budget/lignes/{ligne}/valider', [StructureBudgetApiController::class, 'valider'])->name('api.structure-budget.valider');
            Route::post('structure-budget/lignes/{ligne}/rejeter', [StructureBudgetApiController::class, 'rejeter'])->name('api.structure-budget.rejeter');

            Route::post('besoins/consolidation', [BesoinConsolidationApiController::class, 'store'])->name('api.besoins.consolidation.store');
            Route::post('besoins', [BesoinApiController::class, 'store'])->name('api.besoins.store');
            Route::put('besoins/{besoin}', [BesoinApiController::class, 'update'])->name('api.besoins.update');
            Route::post('besoins/{besoin}/soumettre', [BesoinApiController::class, 'soumettre'])->name('api.besoins.soumettre');
            Route::post('besoins/{besoin}/decisions', [BesoinApiController::class, 'agir'])->name('api.besoins.decisions');
            Route::post('besoins/{besoin}/convertir', [BesoinApiController::class, 'convertir'])->name('api.besoins.convertir');

            Route::post('engagements', [EngagementApiController::class, 'store'])->name('api.engagements.store');
            Route::post('engagements/{engagement}/soumettre', [EngagementApiController::class, 'soumettre'])->name('api.engagements.soumettre');
            Route::post('engagements/{engagement}/decisions', [EngagementApiController::class, 'agir'])->name('api.engagements.decisions');
            Route::post('engagements/{engagement}/degager', [EngagementApiController::class, 'degager'])->name('api.engagements.degager');

            Route::post('depense/{type}', [EtapeDepenseApiController::class, 'store'])->whereIn('type', ['liquidation', 'ordonnancement', 'paiement'])->name('api.depense.store');
            Route::post('depense/{type}/{id}/soumettre', [EtapeDepenseApiController::class, 'soumettre'])->whereIn('type', ['liquidation', 'ordonnancement', 'paiement'])->name('api.depense.soumettre');
            Route::post('depense/{type}/{id}/decisions', [EtapeDepenseApiController::class, 'agir'])->whereIn('type', ['liquidation', 'ordonnancement', 'paiement'])->name('api.depense.decisions');
            Route::post('depense/paiement/{id}/rejet-instrument', [EtapeDepenseApiController::class, 'constatRejetInstrument'])->name('api.depense.rejet-instrument');
            Route::post('pieces/{type}/{id}', [PieceApiController::class, 'store'])->whereIn('type', ['besoin', 'engagement', 'liquidation', 'ordonnancement', 'paiement'])->name('api.pieces.store');
            Route::delete('pieces/{piece}', [PieceApiController::class, 'destroy'])->name('api.pieces.destroy');

            Route::post('rapprochements', [RapprochementApiController::class, 'store'])->name('api.rapprochements.store');
            Route::post('rapprochements/{rapprochement}/importer', [RapprochementApiController::class, 'importer'])->name('api.rapprochements.importer');
            Route::post('rapprochements/{rapprochement}/auto', [RapprochementApiController::class, 'auto'])->name('api.rapprochements.auto');
            Route::post('rapprochements/{rapprochement}/lier', [RapprochementApiController::class, 'lier'])->name('api.rapprochements.lier');
            Route::post('rapprochements/{rapprochement}/cloturer', [RapprochementApiController::class, 'cloturer'])->name('api.rapprochements.cloturer');

            Route::post('utilisateurs', [UtilisateurApiController::class, 'store'])->name('api.utilisateurs.store');
            Route::put('utilisateurs/{utilisateur}', [UtilisateurApiController::class, 'update'])->name('api.utilisateurs.update');
            Route::delete('utilisateurs/{utilisateur}', [UtilisateurApiController::class, 'destroy'])->name('api.utilisateurs.destroy');
            Route::post('utilisateurs/{utilisateur}/statut', [UtilisateurApiController::class, 'changerStatut'])->name('api.utilisateurs.statut');
            Route::post('utilisateurs/{utilisateur}/reinitialiser', [UtilisateurApiController::class, 'reinitialiser'])->name('api.utilisateurs.reinitialiser');
            Route::put('utilisateurs/{utilisateur}/perimetres', [UtilisateurApiController::class, 'enregistrerPerimetres'])->name('api.utilisateurs.perimetres.update');

            Route::post('structures', [StructureApiController::class, 'store'])->name('api.structures.store');
            Route::put('structures/{structure}', [StructureApiController::class, 'update'])->name('api.structures.update');
            Route::delete('structures/{structure}', [StructureApiController::class, 'destroy'])->name('api.structures.destroy');

            Route::post('exercices', [ExerciceApiController::class, 'store'])->name('api.exercices.store');
            Route::put('exercices/{exercice}', [ExerciceApiController::class, 'update'])->name('api.exercices.update');
            Route::delete('exercices/{exercice}', [ExerciceApiController::class, 'destroy'])->name('api.exercices.destroy');
            Route::post('exercices/{exercice}/ouvrir', [ExerciceApiController::class, 'ouvrir'])->name('api.exercices.ouvrir');
            Route::post('exercices/{exercice}/cloturer', [ExerciceApiController::class, 'cloturer'])->name('api.exercices.cloturer');
            Route::post('exercices/{exercice}/activer', [ExerciceApiController::class, 'activer'])->name('api.exercices.activer');

            Route::post('budgets', [BudgetApiController::class, 'store'])->name('api.budgets.store');
            Route::put('budgets/{budget}', [BudgetApiController::class, 'update'])->name('api.budgets.update');
            Route::post('budgets/{budget}/soumettre', [BudgetApiController::class, 'soumettre'])->name('api.budgets.soumettre');
            Route::post('budgets/{budget}/decisions', [BudgetApiController::class, 'agir'])->name('api.budgets.decisions');
            Route::post('budgets/{budget}/revision', [BudgetApiController::class, 'revision'])->name('api.budgets.revision');
            Route::post('budgets/{budget}/preparations/initialiser', [BudgetPreparationApiController::class, 'initialiser'])->name('api.budgets.preparations.initialiser');
            Route::put('budgets/{budget}/preparations/{preparation}', [BudgetPreparationApiController::class, 'update'])->name('api.budgets.preparations.update');
            Route::post('budgets/{budget}/preparations/soumettre', [BudgetPreparationApiController::class, 'soumettre'])->name('api.budgets.preparations.soumettre');
            Route::post('budgets/{budget}/preparations/valider', [BudgetPreparationApiController::class, 'valider'])->name('api.budgets.preparations.valider');
            Route::post('budgets/{budget}/preparations/arbitrer', [BudgetPreparationApiController::class, 'arbitrer'])->name('api.budgets.preparations.arbitrer');
            Route::post('budgets/{budget}/preparations/consolider', [BudgetPreparationApiController::class, 'consolider'])->name('api.budgets.preparations.consolider');
            Route::post('budgets/{budget}/simulations', [BudgetSimulationApiController::class, 'store'])->name('api.budgets.simulations.store');
            Route::post('budgets/{budget}/simulations/{simulation}/calculer', [BudgetSimulationApiController::class, 'calculer'])->name('api.budgets.simulations.calculer');
            Route::post('budgets/{budget}/simulations/{simulation}/retenir', [BudgetSimulationApiController::class, 'retenir'])->name('api.budgets.simulations.retenir');
            Route::post('budgets/{budget}/revisions/rectificatif', [BudgetRevisionApiController::class, 'rectificatif'])->name('api.budgets.revisions.rectificatif');
            Route::post('budgets/{budget}/revisions/virement', [BudgetRevisionApiController::class, 'virement'])->name('api.budgets.revisions.virement');
            Route::post('budgets/{budget}/revisions/lier-pluri', [BudgetRevisionApiController::class, 'lierProgrammation'])->name('api.budgets.revisions.lier-pluri');
            Route::post('budget/credits', [BudgetCreditsApiController::class, 'store'])->name('api.budget.credits.store');
            Route::put('budget/credits/{ligne}', [BudgetCreditsApiController::class, 'update'])->name('api.budget.credits.update');
            Route::delete('budget/credits/{ligne}', [BudgetCreditsApiController::class, 'destroy'])->name('api.budget.credits.destroy');

            Route::post('paps', [PapApiController::class, 'store'])->name('api.paps.store');
            Route::put('paps/{pap}', [PapApiController::class, 'update'])->name('api.paps.update');
            Route::post('paps/{pap}/soumettre', [PapApiController::class, 'soumettre'])->name('api.paps.soumettre');
            Route::post('paps/{pap}/decisions', [PapApiController::class, 'agir'])->name('api.paps.decisions');
            Route::post('paps/{pap}/revision', [PapApiController::class, 'revision'])->name('api.paps.revision');

            Route::post('recettes/{recette}/ventiler', [RecettesApiController::class, 'ventiler'])->name('api.recettes.ventiler');
            Route::post('recettes/encaissements', [RecettesApiController::class, 'storeEncaissement'])->name('api.recettes.encaissements.store');
            Route::post('recettes/encaissements/{encaissement}/soumettre', [RecettesApiController::class, 'soumettreEncaissement'])->name('api.recettes.encaissements.soumettre');
            Route::post('recettes/encaissements/{encaissement}/decisions', [RecettesApiController::class, 'agirEncaissement'])->name('api.recettes.encaissements.decisions');
            Route::post('recettes/encaissements/{encaissement}/rapprocher', [RecettesApiController::class, 'rapprocherEncaissement'])->name('api.recettes.encaissements.rapprocher');
            Route::post('recettes/encaissements/{encaissement}/annuler', [RecettesApiController::class, 'annulerEncaissement'])->name('api.recettes.encaissements.annuler');

            Route::post('campagnes-suivi', [CampagneSuiviApiController::class, 'store'])->name('api.campagnes-suivi.store');
            Route::post('campagnes-suivi/{campagne}/cloturer', [CampagneSuiviApiController::class, 'cloturer'])->name('api.campagnes-suivi.cloturer');
            Route::post('campagnes-suivi/{campagne}/activites/{activite}/collecte', [CampagneSuiviApiController::class, 'enregistrerCollecte'])->name('api.campagnes-suivi.collecte');
            Route::delete('campagnes-suivi/{campagne}', [CampagneSuiviApiController::class, 'destroy'])->name('api.campagnes-suivi.destroy');
            Route::post('recommandations', [RecommandationApiController::class, 'store'])->name('api.recommandations.store');
            Route::put('recommandations/{recommandation}', [RecommandationApiController::class, 'update'])->name('api.recommandations.update');
            Route::delete('recommandations/{recommandation}', [RecommandationApiController::class, 'destroy'])->name('api.recommandations.destroy');
            Route::post('formations', [FormationApiController::class, 'store'])->name('api.formations.store');
            Route::put('formations/{formation}', [FormationApiController::class, 'update'])->name('api.formations.update');
            Route::delete('formations/{formation}', [FormationApiController::class, 'destroy'])->name('api.formations.destroy');
            Route::post('immobilisations', [ImmobilisationApiController::class, 'store'])->name('api.immobilisations.store');
            Route::put('immobilisations/{immobilisation}', [ImmobilisationApiController::class, 'update'])->name('api.immobilisations.update');
            Route::delete('immobilisations/{immobilisation}', [ImmobilisationApiController::class, 'destroy'])->name('api.immobilisations.destroy');
            Route::post('marches', [MarcheApiController::class, 'store'])->name('api.marches.store');
            Route::put('marches/{marche}', [MarcheApiController::class, 'update'])->name('api.marches.update');
            Route::delete('marches/{marche}', [MarcheApiController::class, 'destroy'])->name('api.marches.destroy');
            Route::post('contrats', [ContratApiController::class, 'store'])->name('api.contrats.store');
            Route::put('contrats/{contrat}', [ContratApiController::class, 'update'])->name('api.contrats.update');
            Route::delete('contrats/{contrat}', [ContratApiController::class, 'destroy'])->name('api.contrats.destroy');
            Route::post('appels-fonds', [AppelFondsApiController::class, 'store'])->name('api.appels-fonds.store');
            Route::post('appels-fonds/{appel}/recouvrer', [AppelFondsApiController::class, 'recouvrer'])->name('api.appels-fonds.recouvrer');
            Route::post('appels-fonds/{appel}/relancer', [AppelFondsApiController::class, 'relancer'])->name('api.appels-fonds.relancer');
            Route::post('appels-fonds/{appel}/annuler', [AppelFondsApiController::class, 'annuler'])->name('api.appels-fonds.annuler');
            Route::post('appels-fonds/{appel}/plan-apurement', [PlanApurementApiController::class, 'store'])->name('api.plans-apurement.store');
            Route::post('plans-apurement/{plan}/annuler', [PlanApurementApiController::class, 'annuler'])->name('api.plans-apurement.annuler');
            Route::post('echeances-apurement/{echeance}/regler', [PlanApurementApiController::class, 'reglerEcheance'])->name('api.echeances-apurement.regler');

            Route::post('rbm/niveaux/{niveau}', [RbmNoeudApiController::class, 'store'])->whereIn('niveau', ['pilier', 'axe', 'produit', 'sous_produit', 'activite', 'tache'])->name('api.rbm.niveaux.store');
            Route::put('rbm/niveaux/{niveau}/{id}', [RbmNoeudApiController::class, 'update'])->whereIn('niveau', ['pilier', 'axe', 'produit', 'sous_produit', 'activite', 'tache'])->name('api.rbm.niveaux.update');

            Route::post('ged/documents', [GedApiController::class, 'store'])->name('api.ged.documents.store');
            Route::post('ged/documents/{document:uuid}/signatures', [GedApiController::class, 'demanderSignature'])->name('api.ged.documents.signatures');
            Route::post('ged/signatures/{signature}/signer', [GedApiController::class, 'signer'])->name('api.ged.signatures.signer');
            Route::post('ged/signatures/{signature}/rejeter', [GedApiController::class, 'rejeterSignature'])->name('api.ged.signatures.rejeter');
            Route::post('ged/recherche/sauvegarder', [GedApiController::class, 'sauvegarderRecherche'])->name('api.ged.recherche.sauvegarder');
            Route::delete('ged/recherche/{recherche}', [GedApiController::class, 'supprimerRecherche'])->name('api.ged.recherche.supprimer');
            Route::post('ged/connaissances', [ConnaissanceApiController::class, 'store'])->name('api.ged.connaissances.store');
            Route::put('ged/connaissances/{connaissance}', [ConnaissanceApiController::class, 'update'])->name('api.ged.connaissances.update');
            Route::delete('ged/connaissances/{connaissance}', [ConnaissanceApiController::class, 'destroy'])->name('api.ged.connaissances.destroy');
            Route::post('ged/connaissances/{connaissance}/publier', [ConnaissanceApiController::class, 'publier'])->name('api.ged.connaissances.publier');
            Route::post('ged/connaissances/{connaissance}/archiver', [ConnaissanceApiController::class, 'archiver'])->name('api.ged.connaissances.archiver');
            Route::post('ged/categories', [GedReferentielApiController::class, 'storeCategorie'])->name('api.ged.categories.store');
            Route::post('ged/types', [GedReferentielApiController::class, 'storeType'])->name('api.ged.types.store');
            Route::post('ged/dossiers', [GedReferentielApiController::class, 'storeDossier'])->name('api.ged.dossiers.store');
        });

        Route::middleware('ability.or.session:lecture,suivi')->group(function () {
            Route::prefix('suivi-financier')->name('api.suivi-financier.')->group(function () {
                Route::get('synthese', [SuiviApiController::class, 'syntheseFinanciere'])->name('synthese');
                Route::get('ecarts', [SuiviApiController::class, 'ecartsPhysiqueFinancier'])->name('ecarts');
                Route::get('situations', [SuiviApiController::class, 'situationsBudgetaires'])->name('situations');
                Route::get('restes-engager', [SuiviApiController::class, 'restesAEngager'])->name('restes-engager');
                Route::get('anomalies', [SuiviApiController::class, 'anomaliesFinancieres'])->name('anomalies');
                Route::post('situation-capture', [SuiviApiController::class, 'capturerSituation'])->name('situation.capture');
            });
            Route::prefix('suivi-indicateurs')->name('api.suivi-indicateurs.')->group(function () {
                Route::get('feux', [SuiviApiController::class, 'feuxIndicateurs'])->name('feux');
                Route::get('evolution', [SuiviApiController::class, 'evolutionIndicateurs'])->name('evolution');
                Route::get('performances', [SuiviApiController::class, 'performancesIndicateurs'])->name('performances');
            });
            Route::prefix('suivi-evaluation')->name('api.suivi-evaluation.')->group(function () {
                Route::get('synthese', [SuiviApiController::class, 'syntheseEvaluation'])->name('synthese');
                Route::get('evaluations', [SuiviApiController::class, 'evaluationsPeriodiques'])->name('evaluations');
                Route::get('missions', [SuiviApiController::class, 'missionsSuivi'])->name('missions');
                Route::get('plans', [SuiviApiController::class, 'plansAmelioration'])->name('plans');
                Route::post('evaluations', [SuiviApiController::class, 'genererEvaluation'])->name('evaluations.generer');
            });
        });

        Route::middleware('ability.or.session:lecture')->group(function () {
            Route::prefix('reporting')->name('api.reporting.')->group(function () {
                Route::get('hub', [ReportingApiController::class, 'hub'])->name('hub');
                Route::get('cockpit', [ReportingApiController::class, 'cockpit'])->name('cockpit');
                Route::get('cockpit/live/snapshot', [ReportingApiController::class, 'liveSnapshot'])->name('cockpit.live.snapshot');
                Route::get('cockpit/live/drilldown', [ReportingApiController::class, 'liveDrilldown'])->name('cockpit.live.drilldown');
                Route::get('cockpit/live/ping', [ReportingApiController::class, 'livePing'])->name('cockpit.live.ping');
                Route::get('planification', [ReportingApiController::class, 'planification'])->name('planification');
                Route::get('catalogue', [ReportingApiController::class, 'catalogue'])->name('catalogue');
                Route::get('types', [ReportingApiController::class, 'types'])->name('types');
                Route::get('rapports', [ReportingApiController::class, 'rapports'])->name('rapports');
                Route::get('rapports/{rapport}', [ReportingApiController::class, 'show'])->name('rapport');
                Route::get('modeles', [RapportPersonnaliseApiController::class, 'index'])->name('modeles.index');
                Route::get('modeles/options', [RapportPersonnaliseApiController::class, 'options'])->name('modeles.options');
                Route::get('modeles/{rapportPersonnalise}', [RapportPersonnaliseApiController::class, 'show'])->name('modeles.show');
                Route::get('modeles/{rapportPersonnalise}/generer', [RapportPersonnaliseApiController::class, 'generer'])->name('modeles.generer');
                Route::post('modeles', [RapportPersonnaliseApiController::class, 'store'])->name('modeles.store');
                Route::put('modeles/{rapportPersonnalise}', [RapportPersonnaliseApiController::class, 'update'])->name('modeles.update');
                Route::delete('modeles/{rapportPersonnalise}', [RapportPersonnaliseApiController::class, 'destroy'])->name('modeles.destroy');
            });

            Route::prefix('workflow')->name('api.workflow.')->group(function () {
                Route::get('hub', [WorkflowApiController::class, 'hub'])->name('hub');
                Route::get('audit', [WorkflowApiController::class, 'audit'])->name('audit');
                Route::prefix('admin')->name('admin.')->group(function () {
                    Route::get('options', [WorkflowAdminApiController::class, 'options'])->name('options');
                    Route::get('circuits', [WorkflowAdminApiController::class, 'index'])->name('circuits');
                    Route::post('circuits', [WorkflowAdminApiController::class, 'store'])->name('circuits.store');
                    Route::get('circuits/{workflow}', [WorkflowAdminApiController::class, 'show'])->name('circuit');
                    Route::put('circuits/{workflow}', [WorkflowAdminApiController::class, 'update'])->name('circuit.update');
                    Route::post('circuits/{workflow}/toggle', [WorkflowAdminApiController::class, 'toggle'])->name('circuit.toggle');
                });
                Route::get('circuits', [WorkflowApiController::class, 'circuits'])->name('circuits');
                Route::get('circuits/{code}', [WorkflowApiController::class, 'circuit'])->name('circuit');
                Route::get('instances', [WorkflowApiController::class, 'instances'])->name('instances');
                Route::get('instances/{instance}', [WorkflowApiController::class, 'showInstance'])->name('instance');
                Route::post('instances/{instance}/decisions', [WorkflowApiController::class, 'decision'])->name('decision');
            });

            Route::prefix('notifications')->name('api.notifications.')->group(function () {
                Route::get('/', [NotificationsApiController::class, 'index'])->name('index');
                Route::get('hub', [NotificationsApiController::class, 'hub'])->name('hub');
                Route::get('unread-count', [NotificationsApiController::class, 'unreadCount'])->name('unread');
                Route::get('preferences', [NotificationsApiController::class, 'preferences'])->name('preferences');
                Route::put('preferences', [NotificationsApiController::class, 'enregistrerPreferences'])->name('preferences.update');
                Route::post('tout-lu', [NotificationsApiController::class, 'toutLu'])->name('tout-lu');
                Route::get('templates', [NotificationsApiController::class, 'templates'])->name('templates');
                Route::get('templates/{template}', [NotificationsApiController::class, 'showTemplate'])->name('templates.show');
                Route::put('templates/{template}', [NotificationsApiController::class, 'updateTemplate'])->name('templates.update');
                Route::get('alertes', [NotificationsApiController::class, 'alertes'])->name('alertes');
                Route::post('alertes/{alerte}/resolve', [NotificationsApiController::class, 'resolveAlerte'])->name('alertes.resolve');
                Route::post('{id}/read', [NotificationsApiController::class, 'markRead'])->name('read');
                Route::post('{id}/archiver', [NotificationsApiController::class, 'archiver'])->name('archiver');
            });

            Route::prefix('interoperabilite')->name('api.interoperabilite.')->group(function () {
                Route::get('hub', [InteropApiController::class, 'hub'])->name('hub');
                Route::get('journal', [InteropApiController::class, 'journal'])->name('journal');
                Route::get('monitoring', [InteropApiController::class, 'monitoring'])->name('monitoring');
                Route::get('options', [InteropApiController::class, 'options'])->name('options');
                Route::get('apps', [InteropApiController::class, 'apps'])->name('apps');
                Route::post('apps', [InteropApiController::class, 'storeApp'])->name('apps.store');
                Route::get('apps/{app}', [InteropApiController::class, 'showApp'])->name('apps.show');
                Route::put('apps/{app}', [InteropApiController::class, 'updateApp'])->name('apps.update');
                Route::post('apps/{app}/suspendre', [InteropApiController::class, 'suspendreApp'])->name('apps.suspendre');
                Route::post('apps/{app}/reactiver', [InteropApiController::class, 'reactiverApp'])->name('apps.reactiver');
                Route::post('apps/{app}/revoquer-jetons', [InteropApiController::class, 'revoquerJetonsApp'])->name('apps.jetons');
                Route::post('apps/{app}/clients', [InteropApiController::class, 'storeClient'])->name('apps.clients.store');
                Route::post('apps/{app}/clients/{client}/revoquer', [InteropApiController::class, 'revoquerClient'])->name('apps.clients.revoquer');
                Route::get('webhooks', [InteropApiController::class, 'webhooks'])->name('webhooks');
                Route::get('webhooks/options', [InteropApiController::class, 'webhookOptions'])->name('webhooks.options');
                Route::post('webhooks', [InteropApiController::class, 'storeWebhook'])->name('webhooks.store');
                Route::get('webhooks/{webhook}', [InteropApiController::class, 'showWebhook'])->name('webhooks.show');
                Route::put('webhooks/{webhook}', [InteropApiController::class, 'updateWebhook'])->name('webhooks.update');
                Route::delete('webhooks/{webhook}', [InteropApiController::class, 'destroyWebhook'])->name('webhooks.destroy');
                Route::post('webhooks/{webhook}/tester', [InteropApiController::class, 'testerWebhook'])->name('webhooks.tester');
            });
        });

        Route::middleware('ability:tompro')->group(function () {
            Route::get('tompro/paiements', [TomproController::class, 'paiements'])->name('api.tompro.paiements');
            Route::get('tompro/encaissements', [TomproController::class, 'encaissements'])->name('api.tompro.encaissements');
            Route::get('tompro/synthese', [TomproController::class, 'synthese'])->name('api.tompro.synthese');
            Route::get('tompro/referentiel', [TomproController::class, 'referentiel'])->name('api.tompro.referentiel');
        });
    });
});
