<?php

namespace Database\Seeders;

use Database\Seeders\Demo\ChaineDepenseBulkSeeder;
use Database\Seeders\Demo\ChaineDepenseDemoSeeder;
use Database\Seeders\Demo\ConnaissancesDemoSeeder;
use Database\Seeders\Demo\CompleteDemoSeeder;
use Database\Seeders\Demo\FormationsDemoSeeder;
use Database\Seeders\Demo\ImmobilisationsDemoSeeder;
use Database\Seeders\Demo\IndicateursDemoSeeder;
use Database\Seeders\Demo\InstitutionDemoSeeder;
use Database\Seeders\Demo\InstitutionalShowcaseSeeder;
use Database\Seeders\Demo\MarchesContratsDemoSeeder;
use Database\Seeders\Demo\RapportsPersonnalisesDemoSeeder;
use Database\Seeders\Demo\RecommandationsDemoSeeder;
use Database\Seeders\Demo\SettingsDemoSeeder;
use Database\Seeders\Demo\SuiviDemoSeeder;
use Database\Seeders\Demo\SuiviEvaluationDemoSeeder;
use Database\Seeders\Demo\UsersDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Point d'entree des donnees de demonstration institutionnelles.
 *
 * Ce seeder orchestre uniquement des seeders non destructifs et idempotents.
 * Il separe les referentiels de production de la couche demo exploitable en
 * formation, presentation et tests fonctionnels.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InstitutionDemoSeeder::class,
            UsersDemoSeeder::class,
            InstitutionalShowcaseSeeder::class,
            IndicateursDemoSeeder::class,
            SettingsDemoSeeder::class,
            ChaineDepenseDemoSeeder::class,
            ChaineDepenseBulkSeeder::class,
            SuiviDemoSeeder::class,
            MarchesContratsDemoSeeder::class,
            RecommandationsDemoSeeder::class,
            SuiviEvaluationDemoSeeder::class,
            FormationsDemoSeeder::class,
            ImmobilisationsDemoSeeder::class,
            RapportsPersonnalisesDemoSeeder::class,
            ConnaissancesDemoSeeder::class,
            CompleteDemoSeeder::class,
        ]);
    }
}
