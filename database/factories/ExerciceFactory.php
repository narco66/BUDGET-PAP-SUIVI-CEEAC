<?php

namespace Database\Factories;

use App\Models\Exercice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Exercice> */
class ExerciceFactory extends Factory
{
    protected $model = Exercice::class;

    public function definition(): array
    {
        $annee = fake()->unique()->numberBetween(2024, 2030);

        return [
            'annee' => $annee,
            'code' => 'EX-'.$annee,
            'libelle' => "Exercice budgetaire {$annee}",
            'statut' => 'ouvert',
            'est_actif' => true,
            'date_debut' => $annee.'-01-01',
            'date_fin' => $annee.'-12-31',
            'devise' => 'XAF',
        ];
    }
}
