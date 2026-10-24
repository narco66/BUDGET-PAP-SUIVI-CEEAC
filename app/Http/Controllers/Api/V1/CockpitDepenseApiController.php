<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DepenseExportController;
use App\Models\Exercice;
use App\Services\Depense\DepenseAuditService;
use App\Services\Depense\DepenseReportingService;
use App\Services\TableauBordService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cockpit d’exécution lecture seule — mêmes agrégats que Blade, sans nouveau calcul. */
class CockpitDepenseApiController extends Controller
{
    public function show(
        Request $request,
        TableauBordService $tb,
        DepenseReportingService $reporting,
        DepenseAuditService $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('engagement.voir'), 403);

        $exercices = Exercice::query()->orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? $exercices->firstWhere('statut', 'ouvert')
            ?? $exercices->first();
        $exerciceId = $exercice?->id ?? 0;

        $execution = $exerciceId ? $tb->executionBudgetaire($exerciceId) : [];
        $cles = ['ouvert', 'alloue', 'engage', 'liquide', 'ordonnance', 'paye', 'disponible', 'reste_a_engager', 'reste_a_payer', 'revise'];
        $executionApi = Montant::champs($execution, $cles);
        foreach (['taux_engagement', 'taux_liquidation', 'taux_ordonnancement', 'taux_paiement', 'taux_execution'] as $taux) {
            if (isset($executionApi[$taux])) {
                $executionApi[$taux] = Montant::api($executionApi[$taux], 1);
            }
        }

        $parStatut = $exerciceId
            ? $tb->engagementsParStatut($exerciceId)->map(fn ($s) => [
                'statut' => $s->statut,
                'nombre' => (int) $s->nombre,
                'montant' => Montant::api($s->montant),
            ])->values()
            : [];

        $parTache = $exerciceId
            ? $reporting->executionParTache($exerciceId)->sortByDesc('montant_engage')->take(12)->values()->map(fn ($t) => [
                'tache_code' => $t->tache_code,
                'tache_intitule' => $t->tache_intitule,
                'montant_engage' => Montant::api($t->montant_engage),
                'montant_liquide' => Montant::api($t->montant_liquide),
                'montant_ordonnance' => Montant::api($t->montant_ordonnance),
                'montant_paye' => Montant::api($t->montant_paye),
            ])
            : [];

        $rapport = $exerciceId ? $audit->executer($exerciceId, null, false) : null;

        $user = $request->user();
        $peutExporter = $user !== null && ($user->can('depenses.export') || $user->can('depenses.view'));
        $exports = [];
        if ($peutExporter) {
            foreach (DepenseExportController::catalogue() as $cle => $meta) {
                $exports[] = [
                    'cle' => $cle,
                    'libelle' => $meta['titre'],
                ];
            }
        }

        return response()->json([
            'data' => [
                'exercice' => $exercice ? [
                    'id' => $exercice->id,
                    'annee' => $exercice->annee,
                    'libelle' => $exercice->libelle,
                    'statut' => $exercice->statut,
                ] : null,
                'execution' => $executionApi,
                'par_statut' => $parStatut,
                'par_tache' => $parTache,
                'audit' => $rapport ? [
                    'score_conformite' => (int) $rapport['score_conformite'],
                    'anomalies_total' => (int) $rapport['anomalies_total'],
                    'par_gravite' => $rapport['par_gravite'] ?? [],
                ] : null,
                'exports' => $exports,
            ],
        ]);
    }
}
