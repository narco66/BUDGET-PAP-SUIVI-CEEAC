<?php

namespace Database\Factories;

use App\Models\Activite;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tache> */
class TacheFactory extends Factory
{
    protected $model = Tache::class;

    public function definition(): array
    {
        return [
            'activite_id' => Activite::factory(),
            'responsable_user_id' => User::factory(),
            'code' => 'T-'.fake()->unique()->numberBetween(1000, 9999),
            'intitule' => fake()->randomElement([
                'Elaborer le plan de travail detaille',
                'Collecter les donnees de suivi',
                'Consolider les livrables techniques',
                'Presenter les resultats au comité de pilotage',
            ]),
            'date_debut' => now()->startOfYear()->toDateString(),
            'date_fin' => now()->startOfYear()->addMonths(3)->toDateString(),
            'montant' => fake()->randomElement([5000000, 10000000, 15000000]),
            'taux_realisation' => 50,
            'statut' => 'en_cours',
            'ordre' => fake()->numberBetween(1, 10),
            'statut_referentiel' => 'actif',
        ];
    }
}
