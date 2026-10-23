<?php

namespace Database\Seeders\Demo;

use App\Models\ActionCorrective;
use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Models\MissionSuivi;
use App\Models\PlanAmelioration;
use App\Models\Recommandation;
use App\Models\Structure;
use App\Services\Suivi\SuiviEvaluationService;
use Illuminate\Database\Seeder;

/** Données de démonstration Suivi-Évaluation (phase P3). */
class SuiviEvaluationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        $service = app(SuiviEvaluationService::class);
        $eval = $service->genererEvaluation($exercice->id, null, null);

        $sg = Structure::where('code', 'SG')->value('id');

        $mission = MissionSuivi::firstOrCreate(
            ['numero' => 'MS-2026-001'],
            [
                'exercice_id' => $exercice->id,
                'structure_id' => $sg,
                'evaluation_periodique_id' => $eval->id,
                'type' => 'evaluation',
                'libelle' => 'Évaluation mi-parcours du PAP 2026',
                'objectif' => 'Mesurer la performance globale et identifier les écarts majeurs.',
                'date_debut' => $exercice->date_debut ?? now()->startOfYear(),
                'statut' => 'en_cours',
                'score_mission' => 62,
            ]
        );

        $plan = PlanAmelioration::firstOrCreate(
            ['numero' => 'PA-2026-001'],
            [
                'exercice_id' => $exercice->id,
                'evaluation_periodique_id' => $eval->id,
                'mission_suivi_id' => $mission->id,
                'structure_id' => $sg,
                'intitule' => 'Renforcement du dispositif de collecte des indicateurs',
                'constat' => 'Retards récurrents sur la saisie des mesures IOV et faible couverture des campagnes.',
                'statut' => 'en_cours',
                'taux_realisation' => 45,
            ]
        );

        $rec = Recommandation::firstWhere('numero', 'REC-2026-006');

        ActionCorrective::firstOrCreate(
            [
                'plan_amelioration_id' => $plan->id,
                'libelle' => 'Déployer des points focaux par direction',
            ],
            [
                'recommandation_id' => $rec?->id,
                'description' => 'Nommer et former un point focal S&E par structure.',
                'statut' => 'en_cours',
                'taux' => 50,
            ]
        );

        ActionCorrective::firstOrCreate(
            [
                'plan_amelioration_id' => $plan->id,
                'libelle' => 'Automatiser les rappels de campagne trimestrielle',
            ],
            [
                'statut' => 'en_attente',
                'taux' => 0,
            ]
        );

        $plan->recalculerTaux();

        Recommandation::where('numero', 'REC-2026-008')->update([
            'evaluation_periodique_id' => $eval->id,
            'categorie' => 'institutionnel',
        ]);
    }
}
