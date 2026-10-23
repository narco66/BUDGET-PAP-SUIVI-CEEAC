<?php

namespace Database\Seeders\Demo;

use App\Models\Exercice;
use App\Models\Formation;
use App\Models\SourceFinancement;
use App\Models\Structure;
use Illuminate\Database\Seeder;

/** Ateliers / formations de démonstration (non destructif, idempotent par numéro). */
class FormationsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        $sg = Structure::where('code', 'SG')->value('id');
        $ceeac = SourceFinancement::where('type', 'interne_ceeac')->value('id');
        $ptf = SourceFinancement::where('type', 'externe_ptf')->value('id');

        // [numero, intitule, type, thematique, participants, H, F, cout, satisfaction, statut, source]
        $items = [
            ['FOR-2026-001', 'Atelier de formation sur la GAR', 'atelier', 'Gestion axée sur les résultats', 30, 18, 12, 25_000_000, 87, 'realise', $ptf],
            ['FOR-2026-002', 'Séminaire de planification budgétaire', 'seminaire', 'Budget-programme', 22, 14, 8, 18_000_000, 80, 'realise', $ceeac],
            ['FOR-2026-003', 'Formation aux marchés publics', 'formation', 'Passation des marchés', 18, 10, 8, 12_000_000, 90, 'realise', $ceeac],
            ['FOR-2026-004', 'Conférence régionale sur l’intégration', 'conference', 'Intégration économique', 120, 70, 50, 60_000_000, null, 'planifie', $ptf],
            ['FOR-2026-005', 'Atelier genre et développement', 'atelier', 'Promotion du genre', 40, 12, 28, 15_000_000, 92, 'realise', $ptf],
            ['FOR-2026-006', 'Formation en suivi-évaluation', 'formation', 'Suivi-évaluation des projets', 25, 13, 12, 14_000_000, 78, 'realise', $ceeac],
        ];

        foreach ($items as [$numero, $intitule, $type, $theme, $part, $h, $f, $cout, $satis, $statut, $source]) {
            Formation::firstOrCreate(
                ['numero' => $numero],
                [
                    'exercice_id' => $exercice->id,
                    'structure_id' => $sg,
                    'source_financement_id' => $source,
                    'intitule' => $intitule,
                    'type' => $type,
                    'thematique' => $theme,
                    'lieu' => 'Libreville',
                    'date_debut' => $exercice->date_debut ?? now(),
                    'date_fin' => $exercice->date_debut ?? now(),
                    'nb_participants' => $part,
                    'nb_hommes' => $h,
                    'nb_femmes' => $f,
                    'cout' => $cout,
                    'niveau_satisfaction' => $satis,
                    'statut' => $statut,
                ]
            );
        }
    }
}
