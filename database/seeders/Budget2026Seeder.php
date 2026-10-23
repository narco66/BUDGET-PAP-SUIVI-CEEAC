<?php

namespace Database\Seeders;

use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\Structure;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Reprise du Budget 2026 (part CEEAC-EM) en lignes budgétaires, par nature.
 * Le total des crédits ouverts se réconcilie exactement à 26 275 514 803 XAF
 * (= recettes internes du budget validé à Malabo). Un contrôle est effectué en fin de seed.
 */
class Budget2026Seeder extends Seeder
{
    private const TOTAL_ATTENDU = 26_275_514_803;

    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        $source = SourceFinancement::firstWhere('code', 'CEEAC-EM');
        $sg = Structure::firstWhere('code', 'SG');
        $dpb = Structure::firstWhere('code', 'DEPT-PLAN') ?? $sg;

        if (! $exercice) {
            throw new RuntimeException('Exercice 2026 absent : lancer ExerciceSeeder.');
        }

        // [code ligne, intitulé, code rubrique, montant ouvert, structure]
        $lignes = [
            // --- Dépenses de personnel (11 586 264 803) ---
            ['LB-2026-661', 'Traitements et salaires de base', '661000', 6_499_720_906, $sg],
            ['LB-2026-663', 'Allocations, indemnités et avantages divers', '663000', 3_513_570_961, $sg],
            ['LB-2026-664', 'Cotisations sociales et provision retraite', '664000', 894_672_936, $sg],
            ['LB-2026-665', 'Avantages en nature au personnel', '665000', 333_300_000, $sg],
            ['LB-2026-666', 'Prestations sociales', '666000', 345_000_000, $sg],

            // --- Charges financières (10 000 000) ---
            ['LB-2026-671', 'Intérêts et frais financiers sur la dette', '671000', 10_000_000, $sg],

            // --- Dépenses de biens et services (1 871 250 000) ---
            ['LB-2026-60111', 'Fournitures de bureau', '601100', 50_000_000, $dpb],
            ['LB-2026-60112', 'Consommables informatiques', '601200', 50_000_000, $dpb],
            ['LB-2026-60113', 'Carburant et lubrifiant', '601300', 20_000_000, $dpb],
            ['LB-2026-605', 'Eau, électricité, gaz', '605000', 80_000_000, $dpb],
            ['LB-2026-6111', 'Frais de transports', '611200', 620_000_000, $dpb],
            ['LB-2026-6112', 'Frais de missions', '611300', 450_000_000, $dpb],
            ['LB-2026-612', 'Loyers et charges locatives', '612200', 98_000_000, $dpb],
            ['LB-2026-613', 'Consultants et prestations de services', '622100', 209_000_000, $dpb],
            ['LB-2026-6141', 'Entretien et réparation des biens immobiliers', '614100', 100_000_000, $dpb],
            ['LB-2026-6142', 'Entretien et réparation de matériels et mobiliers', '614200', 46_500_000, $dpb],
            ['LB-2026-6143', 'Maintenance et réparation', '614300', 7_500_000, $dpb],
            ['LB-2026-615', 'Assurances multirisques', '615000', 6_000_000, $dpb],
            ['LB-2026-617', 'Frais de relations publiques et communication', '617100', 22_750_000, $dpb],
            ['LB-2026-618', 'Dépenses de communication', '618100', 111_500_000, $dpb],

            // --- Transferts de fonctionnement (210 000 000) ---
            ['LB-2026-644', 'Transferts aux bureaux de liaison', '644000', 210_000_000, $sg],

            // --- Dotations spéciales aux institutions spécialisées (1 997 000 000) ---
            ['LB-2026-631', 'Dotations spéciales aux institutions spécialisées', '632000', 1_847_000_000, $sg],
            ['LB-2026-639', 'Autres subventions (COPILCER-AC)', '639000', 150_000_000, $sg],

            // --- Assises statutaires et autres dépenses des programmes (1 525 000 000) ---
            ['LB-2026-209', 'Assises statutaires et autres dépenses des programmes', '649000', 1_525_000_000, $sg],

            // --- Dépenses d'équipement (741 000 000) ---
            ['LB-2026-242', 'Équipements informatiques et matériel de bureau', '242000', 320_000_000, $dpb],
            ['LB-2026-213', "Systèmes d'organisation (progiciels)", '213000', 91_000_000, $dpb],
            ['LB-2026-231', 'Aménagements de bâtiments administratifs', '231000', 20_000_000, $dpb],
            ['LB-2026-241', 'Mobilier de bureau', '241000', 110_000_000, $dpb],
            ['LB-2026-243', 'Matériel de transport', '243000', 200_000_000, $dpb],

            // --- Programmes du PAP, part CEEAC (8 335 000 000) ---
            ['LB-2026-PAP1', 'PAP — Pilier 1 : Intégration politique, paix et sécurité', '649000', 1_750_000_000, $sg],
            ['LB-2026-PAP2', 'PAP — Pilier 2 : Intégration économique et commerciale', '649000', 1_750_000_000, $sg],
            ['LB-2026-PAP3', 'PAP — Pilier 3 : Aménagement du territoire et infrastructures', '649000', 1_000_000_000, $sg],
            ['LB-2026-PAP4', 'PAP — Pilier 4 : Intégration environnementale et agriculture', '649000', 1_000_000_000, $sg],
            ['LB-2026-PAP5', 'PAP — Pilier 5 : Promotion du genre et intégration humaine', '649000', 1_350_000_000, $sg],
            ['LB-2026-PAP6', 'PAP — Pilier 6 : Réforme et gouvernance institutionnelle', '649000', 1_485_000_000, $sg],
        ];

        $total = 0;
        foreach ($lignes as [$code, $intitule, $rubriqueCode, $montant, $structure]) {
            $rubrique = BudgetRubrique::where('code', $rubriqueCode)->first();
            if (! $rubrique) {
                throw new RuntimeException("Rubrique {$rubriqueCode} introuvable (lancer NomenclatureBudgetaireSeeder).");
            }

            LigneBudgetaire::updateOrCreateWithTrashed(
                ['exercice_id' => $exercice->id, 'code' => $code],
                [
                    'rubrique_id' => $rubrique->id,
                    'structure_id' => $structure?->id,
                    'source_financement_id' => $source?->id,
                    'intitule' => $intitule,
                    'montant_ouvert' => $montant,
                    'actif' => true,
                    'statut_validation' => 'valide',
                ]
            );
            $total += $montant;
        }

        if ($total !== self::TOTAL_ATTENDU) {
            throw new RuntimeException(sprintf(
                'Contrôle budgétaire échoué : total %s ≠ attendu %s.',
                number_format($total, 0, ',', ' '), number_format(self::TOTAL_ATTENDU, 0, ',', ' ')
            ));
        }
    }
}
