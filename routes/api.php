<?php

use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\BudgetApiController;
use App\Http\Controllers\Api\V1\DonneesApiController;
use App\Http\Controllers\Api\V1\GedApiController;
use App\Http\Controllers\Api\V1\PapApiController;
use App\Http\Controllers\Api\V1\RbmApiController;
use App\Http\Controllers\Api\V1\RecettesApiController;
use App\Http\Controllers\Api\V1\NotificationsApiController;
use App\Http\Controllers\Api\V1\ReportingApiController;
use App\Http\Controllers\Api\V1\StructureBudgetApiController;
use App\Http\Controllers\Api\V1\SuiviApiController;
use App\Http\Controllers\Api\V1\TomproController;
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

    Route::middleware(['auth:sanctum', 'api.connected', 'api.log', 'throttle:api'])->group(function () {
        Route::get('me', [AuthApiController::class, 'me'])->name('api.me');
        Route::post('logout', [AuthApiController::class, 'logout'])->name('api.logout');

        Route::middleware('ability:lecture')->group(function () {
            Route::get('exercices', [DonneesApiController::class, 'exercices'])->name('api.exercices');
            Route::get('structures', [DonneesApiController::class, 'structures'])->name('api.structures');
            Route::get('institution', [DonneesApiController::class, 'institution'])->name('api.institution');
            Route::get('institution/gouvernance', [DonneesApiController::class, 'gouvernance'])->name('api.institution.gouvernance');
            Route::get('lignes-budgetaires', [DonneesApiController::class, 'lignesBudgetaires'])->name('api.lignes');
            Route::get('structure-budget/synthese', [StructureBudgetApiController::class, 'synthese'])->name('api.structure-budget.synthese');
            Route::get('structure-budget/arbre', [StructureBudgetApiController::class, 'arbre'])->name('api.structure-budget.arbre');
            Route::get('structure-budget/lignes', [StructureBudgetApiController::class, 'lignes'])->name('api.structure-budget.lignes');
            Route::get('structure-budget/lignes/{ligne}', [StructureBudgetApiController::class, 'showLigne'])->name('api.structure-budget.ligne');
            Route::get('engagements', [DonneesApiController::class, 'engagements'])->name('api.engagements');
            Route::get('rbm/piliers', [RbmApiController::class, 'piliers'])->name('api.rbm.piliers');
            Route::get('rbm/matrice', [RbmApiController::class, 'matrice'])->name('api.rbm.matrice');
            Route::get('rbm/indicateurs', [RbmApiController::class, 'indicateurs'])->name('api.rbm.indicateurs');
            Route::get('budgets', [BudgetApiController::class, 'index'])->name('api.budgets.index');
            Route::get('budgets/{budget}', [BudgetApiController::class, 'show'])->name('api.budgets.show');
            Route::get('budgets/{budget}/synthese', [BudgetApiController::class, 'synthese'])->name('api.budgets.synthese');
            Route::get('paps', [PapApiController::class, 'index'])->name('api.paps.index');
            Route::get('paps/{pap}', [PapApiController::class, 'show'])->name('api.paps.show');
            Route::get('recettes', [RecettesApiController::class, 'recettes'])->name('api.recettes.index');
            Route::get('recettes/encaissements', [RecettesApiController::class, 'encaissements'])->name('api.recettes.encaissements');
            Route::get('recettes/{recette}', [RecettesApiController::class, 'showRecette'])->name('api.recettes.show');
            Route::get('ged/documents', [GedApiController::class, 'index'])->name('api.ged.documents.index');
            Route::get('ged/documents/{document:uuid}', [GedApiController::class, 'show'])->name('api.ged.documents.show');
        });

        Route::post('structure-budget/lignes/{ligne}/soumettre', [StructureBudgetApiController::class, 'soumettre'])->name('api.structure-budget.soumettre');
        Route::post('structure-budget/lignes/{ligne}/valider', [StructureBudgetApiController::class, 'valider'])->name('api.structure-budget.valider');
        Route::post('structure-budget/lignes/{ligne}/rejeter', [StructureBudgetApiController::class, 'rejeter'])->name('api.structure-budget.rejeter');

        Route::prefix('suivi-financier')->name('api.suivi-financier.')->group(function () {
            Route::get('synthese', [SuiviApiController::class, 'syntheseFinanciere'])->name('synthese');
            Route::get('ecarts', [SuiviApiController::class, 'ecartsPhysiqueFinancier'])->name('ecarts');
            Route::get('situations', [SuiviApiController::class, 'situationsBudgetaires'])->name('situations');
            Route::get('restes-engager', [SuiviApiController::class, 'restesAEngager'])->name('restes-engager');
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
        });

        Route::prefix('reporting')->name('api.reporting.')->group(function () {
            Route::get('catalogue', [ReportingApiController::class, 'catalogue'])->name('catalogue');
            Route::get('types', [ReportingApiController::class, 'types'])->name('types');
            Route::get('rapports', [ReportingApiController::class, 'rapports'])->name('rapports');
            Route::get('rapports/{rapport}', [ReportingApiController::class, 'show'])->name('rapport');
        });

        Route::prefix('workflow')->name('api.workflow.')->group(function () {
            Route::get('circuits', [WorkflowApiController::class, 'circuits'])->name('circuits');
            Route::get('circuits/{code}', [WorkflowApiController::class, 'circuit'])->name('circuit');
            Route::get('instances', [WorkflowApiController::class, 'instances'])->name('instances');
            Route::get('instances/{instance}', [WorkflowApiController::class, 'showInstance'])->name('instance');
            Route::post('instances/{instance}/decisions', [WorkflowApiController::class, 'decision'])->name('decision');
        });

        Route::prefix('notifications')->name('api.notifications.')->group(function () {
            Route::get('/', [NotificationsApiController::class, 'index'])->name('index');
            Route::get('unread-count', [NotificationsApiController::class, 'unreadCount'])->name('unread');
            Route::post('{id}/read', [NotificationsApiController::class, 'markRead'])->name('read');
            Route::get('alertes', [NotificationsApiController::class, 'alertes'])->name('alertes');
            Route::post('alertes/{alerte}/resolve', [NotificationsApiController::class, 'resolveAlerte'])->name('alertes.resolve');
        });

        Route::middleware('ability:tompro')->group(function () {
            Route::get('tompro/paiements', [TomproController::class, 'paiements'])->name('api.tompro.paiements');
            Route::get('tompro/encaissements', [TomproController::class, 'encaissements'])->name('api.tompro.encaissements');
            Route::get('tompro/synthese', [TomproController::class, 'synthese'])->name('api.tompro.synthese');
            Route::get('tompro/referentiel', [TomproController::class, 'referentiel'])->name('api.tompro.referentiel');
        });
    });
});
