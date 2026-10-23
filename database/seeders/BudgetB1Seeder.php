<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\Structure;
use App\Services\Budget\BudgetEnveloppeService;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Phase B1 — enveloppe budgétaire institutionnelle pour l'exercice courant. */
class BudgetB1Seeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            throw new RuntimeException('Exercice 2026 absent.');
        }

        $structure = Structure::firstWhere('code', 'DIR-PPB');

        Budget::updateOrCreate(
            ['exercice_id' => $exercice->id],
            [
                'reference' => 'BUDGET-'.$exercice->annee,
                'libelle' => 'Budget institutionnel '.$exercice->annee,
                'structure_id' => $structure?->id,
                'statut' => 'brouillon',
                'date_elaboration' => now()->toDateString(),
                'montant_total' => app(BudgetEnveloppeService::class)->calculerMontantTotal($exercice->id),
            ]
        );
    }
}
