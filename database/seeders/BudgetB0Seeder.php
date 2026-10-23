<?php

namespace Database\Seeders;

use App\Models\Exercice;
use App\Services\Budget\BudgetCompletenessService;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Phase B0 — rapprochements PAP, ventilations PTF et cohérence RBM ↔ budget. */
class BudgetB0Seeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            throw new RuntimeException('Exercice 2026 absent.');
        }

        $stats = app(BudgetCompletenessService::class)->completer($exercice->id);

        if (! ($stats['succes'] ?? false)) {
            throw new RuntimeException(
                'Complétion B0 échouée : '.$stats['anomalies_total'].' anomalie(s) budget:audit.'
            );
        }
    }
}
