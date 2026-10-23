<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Budget> */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $annee = fake()->unique()->numberBetween(2024, 2035);

        return [
            'exercice_id' => Exercice::factory(['annee' => $annee, 'statut' => 'ouvert']),
            'reference' => 'BUD-'.$annee.'-CEEAC',
            'libelle' => 'Budget institutionnel CEEAC '.$annee,
            'structure_id' => Structure::factory(),
            'responsable_id' => User::factory(),
            'date_elaboration' => $annee.'-01-10',
            'observations' => 'Budget de demonstration coherent avec le PAP et la chaine de depense.',
            'statut' => 'brouillon',
            'version' => 1,
            'cree_par' => User::factory(),
            'montant_total' => 0,
        ];
    }

    public function adopte(float $montant = 500000000): self
    {
        return $this->state(fn () => [
            'statut' => 'adopte',
            'adopte_le' => now(),
            'validated_at' => now(),
            'montant_total' => $montant,
        ]);
    }
}
