<?php

namespace Database\Seeders\Demo;

use App\Models\Exercice;
use App\Models\Recommandation;
use App\Models\Structure;
use Illuminate\Database\Seeder;

/** Recommandations institutionnelles de démonstration (non destructif, idempotent par numéro). */
class RecommandationsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        $sg = Structure::where('code', 'SG')->value('id');
        $budget = Structure::where('code', 'DIR-BUDGET')->value('id');

        // [numero, source, reference, libelle, priorite, statut, taux, structure]
        $items = [
            ['REC-2026-001', 'conference_chefs_etat', 'Sommet ordinaire 2026', "Accélérer la mise en œuvre de la zone de libre-échange régionale", 'haute', 'en_cours', 45, $sg],
            ['REC-2026-002', 'conference_chefs_etat', 'Sommet ordinaire 2026', "Renforcer le financement autonome de la Communauté", 'haute', 'en_cours', 60, $budget],
            ['REC-2026-003', 'conseil_ministres', '24ᵉ session ordinaire', "Adopter le cadre harmonisé de gestion budgétaire axée sur les résultats", 'moyenne', 'realisee', 100, $budget],
            ['REC-2026-004', 'conseil_ministres', '24ᵉ session ordinaire', "Élaborer un plan de formation continue du personnel", 'moyenne', 'en_cours', 30, $sg],
            ['REC-2026-005', 'reunion_statutaire', 'Comité des experts', "Mettre à jour la nomenclature budgétaire selon le SYSCOHADA révisé", 'moyenne', 'realisee', 100, $budget],
            ['REC-2026-006', 'mission_audit', 'Audit interne 2025', "Renforcer le contrôle interne sur la chaîne de la dépense", 'haute', 'en_cours', 55, $budget],
            ['REC-2026-007', 'mission_audit', 'Audit interne 2025', "Régulariser les engagements provisionnels en fin d'exercice", 'haute', 'en_attente', 0, $budget],
            ['REC-2026-008', 'reunion_statutaire', 'Comité de pilotage PAP', "Améliorer la collecte des indicateurs de performance", 'basse', 'non_realisee', 10, $sg],
        ];

        foreach ($items as [$numero, $source, $ref, $libelle, $priorite, $statut, $taux, $structureId]) {
            Recommandation::firstOrCreate(
                ['numero' => $numero],
                [
                    'exercice_id' => $exercice->id,
                    'structure_id' => $structureId,
                    'source' => $source,
                    'reference_reunion' => $ref,
                    'date_emission' => $exercice->date_debut ?? now(),
                    'libelle' => $libelle,
                    'priorite' => $priorite,
                    'echeance' => $exercice->date_fin ?? now()->addMonths(6),
                    'statut' => $statut,
                    'taux_execution' => $taux,
                ]
            );
        }
    }
}
