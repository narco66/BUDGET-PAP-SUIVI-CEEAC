<?php

namespace App\Http\Controllers;

use App\Services\AdministrationTechnique\SanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Centre de supervision technique (Lot T1) : cockpit de santé de la plateforme + endpoint /health.
 * Permissions `admin_technique.*`.
 */
class AdministrationTechniqueController extends Controller
{
    /** Modules supervisés → table représentative (volumétrie). */
    private const MODULES = [
        'PAP' => 'activites', 'Budget' => 'lignes_budgetaires', 'Recettes' => 'recettes',
        'Engagements' => 'engagements', 'Paiements' => 'paiements', 'GED' => 'pieces_justificatives',
        'Workflow' => 'workflow_instances', 'API (journal)' => 'api_logs',
    ];

    public function cockpit(Request $request, SanteService $sante): View
    {
        abort_unless($request->user()->can('admin_technique.view') || $request->user()->can('admin_technique.monitor'), 403);

        $controles = $sante->controles();

        // Volumétrie des modules : comptages regroupés en une seule requête (hasTable est très peu coûteux ;
        // Schema::getTables() calcule la taille de chaque table et coûtait ~185 ms).
        $presentes = array_filter(self::MODULES, fn ($table) => Schema::hasTable($table));
        $comptes = $presentes === [] ? [] : (array) DB::selectOne('select '.implode(', ', array_map(
            fn ($table, $i) => '(select count(*) from '.DB::getQueryGrammar()->wrapTable($table).") as c{$i}",
            $presentes, array_keys(array_values($presentes)))));
        $supervision = [];
        $i = 0;
        foreach (self::MODULES as $module => $table) {
            $supervision[$module] = isset($presentes[$module]) ? (int) $comptes['c'.$i++] : null;
        }

        return view('administration-technique.cockpit', [
            'controles' => $controles,
            'statutGlobal' => $sante->statutGlobal($controles),
            'derniereCapture' => $sante->derniereCapture(),
            'historique' => $sante->historique(20),
            'jobsEchoues' => $sante->jobsEchoues(),
            'supervision' => $supervision,
            'incidentsOuverts' => Schema::hasTable('incidents') ? \App\Models\Incident::ouvert()->count() : 0,
            'incidentsCritiques' => Schema::hasTable('incidents') ? \App\Models\Incident::where('gravite', 'critique')->ouvert()->count() : 0,
            'derniereSauvegarde' => Schema::hasTable('backups') ? \App\Models\Backup::where('statut', 'succes')->orderByDesc('id')->first() : null,
        ]);
    }

    /** Endpoint de santé (sondes externes / load-balancer). Minimal, sans détail sensible. */
    public function health(SanteService $sante): JsonResponse
    {
        $controles = $sante->controles();
        $statut = $sante->statutGlobal($controles);

        return response()->json([
            'statut' => $statut,
            'horodatage' => now()->toIso8601String(),
            'controles' => collect($controles)->map(fn ($c) => ['cle' => $c['cle'], 'statut' => $c['statut']])->all(),
        ], $statut === 'critique' ? 503 : 200);
    }
}
