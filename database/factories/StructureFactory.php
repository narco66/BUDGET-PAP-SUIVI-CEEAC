<?php

namespace Database\Factories;

use App\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Structure> */
class StructureFactory extends Factory
{
    protected $model = Structure::class;

    public function definition(): array
    {
        $departements = [
            ['APPS', 'Affaires politiques, paix et securite'],
            ['MCAEMF', 'Marche commun, affaires economiques, monetaires et financieres'],
            ['ERNADR', 'Environnement, ressources naturelles et developpement rural'],
            ['ATI', 'Amenagement du territoire et infrastructures'],
            ['PGDHS', 'Promotion du genre, developpement humain et social'],
        ];
        [$sigle, $libelle] = fake()->randomElement($departements);

        return [
            'code' => $sigle.'-'.fake()->unique()->numberBetween(100, 999),
            'libelle' => 'Departement '.$libelle,
            'sigle' => $sigle,
            'type' => 'departement',
            'niveau' => 2,
            'ordre' => fake()->numberBetween(1, 20),
            'description' => 'Structure institutionnelle de demonstration CEEAC.',
            'actif' => true,
        ];
    }

    public function direction(?Structure $parent = null): self
    {
        return $this->state(fn () => [
            'parent_id' => $parent?->id ?? Structure::factory()->create(['type' => 'departement'])->id,
            'type' => 'direction',
            'niveau' => 3,
            'code' => 'DIR-'.fake()->unique()->numberBetween(100, 999),
            'libelle' => 'Direction programmation, budget et suivi',
            'sigle' => 'DPBS',
        ]);
    }

    public function service(?Structure $parent = null): self
    {
        return $this->state(fn () => [
            'parent_id' => $parent?->id ?? Structure::factory()->direction()->create()->id,
            'type' => 'service',
            'niveau' => 4,
            'code' => 'SVC-'.fake()->unique()->numberBetween(100, 999),
            'libelle' => 'Service suivi et reporting de la performance',
            'sigle' => 'SSRP',
        ]);
    }
}
