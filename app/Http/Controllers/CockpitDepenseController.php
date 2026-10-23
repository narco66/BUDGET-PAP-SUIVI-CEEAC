<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Services\Depense\DepenseAuditService;
use App\Services\Depense\DepenseReportingService;
use App\Services\TableauBordService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cockpit d'exécution de la dépense — tableau de bord consolidé de la chaîne
 * Engagement → Liquidation → Ordonnancement → Paiement.
 * Lecture seule : réutilise les services existants (aucune nouvelle table, aucun calcul dupliqué).
 */
class CockpitDepenseController extends Controller
{
    public function index(
        Request $request,
        TableauBordService $tb,
        DepenseReportingService $reporting,
        DepenseAuditService $audit,
    ): View {
        abort_unless($request->user()->can('engagement.voir'), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? $exercices->firstWhere('statut', 'ouvert')
            ?? $exercices->first();
        $exerciceId = $exercice?->id ?? 0;

        $rapportAudit = $exerciceId ? $audit->executer($exerciceId, null, false) : null;

        return view('depense.cockpit', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'execution' => $tb->executionBudgetaire($exerciceId),
            'parStatut' => $tb->engagementsParStatut($exerciceId),
            'parTache' => $exerciceId
                ? $reporting->executionParTache($exerciceId)->sortByDesc('montant_engage')->take(12)->values()
                : collect(),
            'audit' => $rapportAudit,
        ]);
    }
}
