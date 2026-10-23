<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Exercice;
use App\Services\Budget\BudgetPreparationService;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Phase B2 — initialise les propositions budgétaires pour l'exercice courant. */
class BudgetB2Seeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            throw new RuntimeException('Exercice 2026 absent.');
        }

        $budget = Budget::firstWhere('exercice_id', $exercice->id);
        if (! $budget) {
            throw new RuntimeException('Enveloppe budget 2026 absente — exécutez BudgetB1Seeder.');
        }

        app(BudgetPreparationService::class)->initialiser($budget);
    }
}
