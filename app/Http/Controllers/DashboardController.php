<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\User;
use App\Services\TableauBordService;
use App\Support\PersonaTableauxBord;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly TableauBordService $tb)
    {
    }

    public function index(): View
    {
        $exercice = Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->first();
        $exId = $exercice?->id ?? 0;

        $budget = $this->tb->executionBudgetaire($exId);
        $pap = $this->tb->executionPap($exId);

        $kpis = [
            'structures' => Structure::actif()->count(),
            'utilisateurs' => User::where('actif', true)->count(),
            'contributions' => (float) Contribution::where('exercice_id', $exId)->sum('montant_appele'),
            'credits_ouverts' => $budget['ouvert'],
            'credits_engages' => $budget['engage'],
            'disponible' => $budget['disponible'],
        ];

        // Derniers engagements pour le fil d'activité
        $derniersEngagements = Engagement::with('expressionBesoin')
            ->where('exercice_id', $exId)
            ->latest('date_engagement')->latest('id')
            ->limit(6)->get();

        return view('dashboard', [
            'exercice' => $exercice,
            'persona' => PersonaTableauxBord::pour(auth()->user()),
            'kpis' => $kpis,
            'budget' => $budget,
            'pap' => $pap,
            'parNature' => $this->tb->consommationParNature($exId),
            'engagements' => $this->tb->engagementsParStatut($exId),
            'papParPilier' => $this->tb->papParPilier($exId),
            'feux' => $this->tb->feuxIndicateurs($exId ?: null),
            'derniersEngagements' => $derniersEngagements,
        ]);
    }
}
