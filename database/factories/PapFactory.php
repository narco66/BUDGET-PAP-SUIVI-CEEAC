<?php

namespace Database\Factories;

use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pap> */
class PapFactory extends Factory
{
    protected $model = Pap::class;

    public function definition(): array
    {
        $exercice = Exercice::factory();

        return [
            'exercice_id' => $exercice,
            'reference' => 'PAP-'.fake()->unique()->numberBetween(2024, 2035).'-CEEAC',
            'libelle' => 'Plan annuel de performance institutionnel CEEAC',
            'structure_id' => Structure::factory(),
            'responsable_id' => User::factory(),
            'date_elaboration' => now()->startOfYear()->addDays(10)->toDateString(),
            'observations' => 'PAP de demonstration structure autour des priorites communautaires.',
            'statut' => 'brouillon',
            'version' => 1,
            'cree_par' => User::factory(),
        ];
    }

    public function publie(): self
    {
        return $this->state(fn () => [
            'statut' => 'publie',
            'publie_le' => now(),
            'validated_at' => now(),
        ]);
    }
}
