<?php

namespace Database\Factories;

use App\Models\Axe;
use App\Models\Produit;
use App\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Produit> */
class ProduitFactory extends Factory
{
    protected $model = Produit::class;

    public function definition(): array
    {
        return [
            'axe_id' => Axe::factory(),
            'structure_id' => Structure::factory(),
            'code' => 'PR-'.fake()->unique()->numberBetween(100, 999),
            'intitule' => fake()->randomElement([
                'Cadre regional de coordination operationnel',
                'Mecanisme de suivi des programmes communautaires renforce',
                'Outils de pilotage budgetaire harmonises',
                'Dispositif de reporting institutionnel consolide',
            ]),
            'description' => 'Produit RBM/GAR de demonstration relie au PAP et au budget.',
            'ordre' => fake()->numberBetween(1, 10),
            'statut' => 'actif',
        ];
    }
}
