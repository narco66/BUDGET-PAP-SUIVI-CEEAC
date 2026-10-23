<?php

namespace Database\Factories;

use App\Models\Produit;
use App\Models\SousProduit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SousProduit> */
class SousProduitFactory extends Factory
{
    protected $model = SousProduit::class;

    public function definition(): array
    {
        return [
            'produit_id' => Produit::factory(),
            'code' => 'SP-'.fake()->unique()->numberBetween(100, 999),
            'intitule' => fake()->randomElement([
                'Plans de travail departementaux disponibles',
                'Rapports trimestriels consolides et valides',
                'Outils de collecte harmonises deployes',
                'Tableaux de bord de performance alimentes',
            ]),
            'description' => 'Sous-produit de demonstration rattachant activites et indicateurs.',
            'ordre' => fake()->numberBetween(1, 10),
            'statut' => 'actif',
        ];
    }
}
