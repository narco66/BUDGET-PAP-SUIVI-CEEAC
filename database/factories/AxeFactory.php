<?php

namespace Database\Factories;

use App\Models\Axe;
use App\Models\Pilier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Axe> */
class AxeFactory extends Factory
{
    protected $model = Axe::class;

    public function definition(): array
    {
        return [
            'pilier_id' => Pilier::factory(),
            'code' => 'A-'.fake()->unique()->numberBetween(100, 999),
            'intitule' => fake()->randomElement([
                'Renforcement du pilotage de la performance',
                'Amelioration de l execution des programmes communautaires',
                'Modernisation des outils de planification et de reporting',
                'Consolidation du suivi des engagements regionaux',
            ]),
            'objectif_strategique' => 'Ameliorer la qualite de la planification, du suivi et de la redevabilite.',
            'ordre' => fake()->numberBetween(1, 20),
            'statut' => 'actif',
        ];
    }
}
