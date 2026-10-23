<?php

namespace Database\Factories;

use App\Models\Exercice;
use App\Models\Pilier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pilier> */
class PilierFactory extends Factory
{
    protected $model = Pilier::class;

    public function definition(): array
    {
        return [
            'exercice_id' => Exercice::factory(),
            'code' => 'P-'.fake()->unique()->numberBetween(100, 999),
            'intitule' => fake()->randomElement([
                'Integration regionale et marche commun',
                'Paix, securite et gouvernance regionale',
                'Infrastructures communautaires et connectivite',
                'Developpement humain, genre et inclusion sociale',
            ]),
            'description' => 'Pilier PAP de demonstration aligne aux priorites institutionnelles CEEAC.',
            'ordre' => fake()->numberBetween(1, 20),
            'statut' => 'actif',
        ];
    }
}
