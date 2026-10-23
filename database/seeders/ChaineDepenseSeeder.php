<?php

namespace Database\Seeders;

use Database\Seeders\Demo\ChaineDepenseDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Seeder officiel de la chaîne de la dépense (phase D0).
 * Charge les prérequis puis les dossiers de démonstration couvrant tous les cas métier.
 */
class ChaineDepenseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepensePermissionsSeeder::class,
            StructureSeeder::class,
            ExerciceSeeder::class,
            NomenclatureBudgetaireSeeder::class,
            Pap2026Seeder::class,
            Budget2026Seeder::class,
            WorkflowSeeder::class,
            ChaineDepenseDemoSeeder::class,
        ]);
    }
}
