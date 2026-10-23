<?php

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Engagement> */
class EngagementFactory extends Factory
{
    protected $model = Engagement::class;

    public function definition(): array
    {
        $besoin = ExpressionBesoin::factory()->valide()->create();
        $montant = (float) $besoin->montant_estime;

        return [
            'numero' => 'ENG-'.now()->year.'-'.fake()->unique()->numberBetween(1000, 9999),
            'exercice_id' => $besoin->exercice_id,
            'expression_besoin_id' => $besoin->id,
            'activite_id' => $besoin->activite_id,
            'tache_id' => $besoin->tache_id,
            'type' => 'simple',
            'objet' => $besoin->objet,
            'beneficiaire' => fake()->randomElement([
                'Commission de la CEEAC',
                'Expertise regionale CEEAC',
                'Prestataire institutionnel agree',
            ]),
            'montant_total' => $montant,
            'date_engagement' => now()->toDateString(),
            'statut' => 'brouillon',
            'cree_par' => User::factory(),
            'credits_reserves' => false,
        ];
    }

    public function vise(): self
    {
        return $this->state(fn () => [
            'statut' => 'vise',
            'controleur_id' => User::factory(),
            'vise_le' => now(),
        ]);
    }
}
