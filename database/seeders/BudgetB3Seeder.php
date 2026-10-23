<?php



namespace Database\Seeders;



use App\Models\Budget;

use App\Models\Exercice;

use App\Services\Budget\BudgetSimulationService;

use Illuminate\Database\Seeder;

use RuntimeException;



/** Phase B3 — scénarios de démonstration pour l'exercice courant. */

class BudgetB3Seeder extends Seeder

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



        $service = app(BudgetSimulationService::class);



        foreach ([

            ['libelle' => 'Réduction globale 5 %', 'type' => 'global_pct', 'parametres' => ['taux' => -5], 'hypothese' => 'Contrainte de ressources — scénario prudent'],

            ['libelle' => 'Croissance modérée +3 %', 'type' => 'global_pct', 'parametres' => ['taux' => 3], 'hypothese' => 'Hypothèse ressources stables'],

        ] as $data) {

            if ($service->simulations($budget)->where('libelle', $data['libelle'])->isNotEmpty()) {

                continue;

            }



            $sim = $service->creer($budget, $data, 1);

            $service->calculer($sim, 1);

        }

    }

}

