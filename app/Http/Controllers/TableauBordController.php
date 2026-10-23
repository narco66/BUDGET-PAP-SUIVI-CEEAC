<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Services\Recettes\GestionRecettesService;
use App\Services\Recettes\RecettesReportingService;
use App\Services\TableauBordService;
use App\Services\Tresorerie\TresorerieService;
use App\Support\PersonaTableauxBord;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableauBordController extends Controller
{
    public function __construct(
        private readonly TableauBordService $tb,
        private readonly GestionRecettesService $recettes,
        private readonly RecettesReportingService $reportingRecettes,
        private readonly TresorerieService $tresorerie,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('tableau_bord.voir'), 403);

        $exercice = $request->filled('exercice')
            ? Exercice::find($request->exercice)
            : (Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->first());

        $exId = $exercice?->id ?? 0;

        // Synthèse Recettes (affichée si l'utilisateur a la permission de consultation).
        $recettes = null;
        if ($request->user()->can('recette.voir')) {
            $synth = $this->recettes->syntheseExercice($exId);
            $mob = $this->reportingRecettes->syntheseMobilisation($exId);
            $recettes = [
                'prevu' => $synth['montant_prevu'],
                'realise' => $synth['montant_realise'],
                'taux' => $synth['montant_prevu'] > 0 ? round($synth['montant_realise'] / $synth['montant_prevu'] * 100, 1) : 0,
                'appele' => $mob['appele'],
                'recouvre' => $mob['recouvre'],
                'arriere' => $mob['arriere'],
                'taux_recouvrement' => $mob['taux'],
            ];
        }

        return view('tableaux.index', [
            'persona' => PersonaTableauxBord::pour($request->user()),
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'budget' => $this->tb->executionBudgetaire($exId),
            'parNature' => $this->tb->consommationParNature($exId),
            'parStructure' => $this->tb->consommationParStructure($exId),
            'pap' => $this->tb->executionPap($exId),
            'engagements' => $this->tb->engagementsParStatut($exId),
            'feux' => $this->tb->feuxIndicateurs(),
            'recettes' => $recettes,
        ]);
    }

    /** Cockpit trésorerie unifié recettes / dépenses (P2-5). */
    public function tresorerie(Request $request): View
    {
        abort_unless($request->user()->can('tableau_bord.voir'), 403);

        $exercice = $request->filled('exercice')
            ? Exercice::find($request->exercice)
            : (Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->first());

        $exId = $exercice?->id ?? 0;

        return view('tableaux.tresorerie', [
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'synthese' => $this->tresorerie->synthese($exId),
        ]);
    }
}
