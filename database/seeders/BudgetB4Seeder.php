<?php



namespace Database\Seeders;



use App\Models\Budget;

use App\Models\Exercice;

use App\Models\Strategie\ProgrammationPluriannuelle;

use App\Services\Budget\BudgetPluriannuelService;

use Illuminate\Database\Seeder;

use RuntimeException;



/** Phase B4 — lien pluriannuel et préparation révisions pour l'exercice courant. */

class BudgetB4Seeder extends Seeder

{

    public function run(): void

    {

        $exercice = Exercice::firstWhere('annee', 2026);

        if (! $exercice) {

            throw new RuntimeException('Exercice 2026 absent.');

        }



        $budget = Budget::firstWhere('exercice_id', $exercice->id);

        if (! $budget) {

            throw new RuntimeException('Enveloppe budget 2026 absente.');

        }



        $pluri = ProgrammationPluriannuelle::firstWhere('reference', 'PROG-PLURI-2026-2030');

        if ($pluri && ! $budget->programmation_pluriannuelle_id) {

            app(BudgetPluriannuelService::class)->lier($budget, $pluri->id, null, 1);

        }

    }

}

