<?php

namespace Database\Factories;

use App\Models\Activite;
use App\Models\SousProduit;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Activite> */
class ActiviteFactory extends Factory
{
    protected $model = Activite::class;

    public function definition(): array
    {
        $sousProduit = SousProduit::factory()->create();
        $montant = fake()->randomElement([25000000, 50000000, 75000000, 120000000]);

        return [
            'sous_produit_id' => $sousProduit->id,
            'produit_id' => $sousProduit->produit_id,
            'structure_id' => Structure::factory(),
            'responsable_user_id' => User::factory(),
            'code' => 'ACT-'.fake()->unique()->numberBetween(1000, 9999),
            'intitule' => fake()->randomElement([
                'Coordination technique des programmes communautaires',
                'Suivi trimestriel de l execution budgetaire',
                'Organisation des revues de performance sectorielle',
                'Production des rapports de pilotage institutionnel',
            ]),
            'description' => 'Activite operationnelle de demonstration alignee au PAP.',
            'montant_total' => $montant,
            'montant_ceeac' => round($montant * 0.7, 2),
            'montant_ptf' => round($montant * 0.3, 2),
            'trim_1' => true,
            'trim_2' => true,
            'trim_3' => true,
            'trim_4' => true,
            'taux_realisation_physique' => 55,
            'taux_realisation_financier' => 45,
            'ordre' => fake()->numberBetween(1, 20),
            'statut' => 'actif',
        ];
    }

    public function critique(): self
    {
        return $this->state(fn () => [
            'taux_realisation_physique' => 28,
            'taux_realisation_financier' => 18,
            'cause_ecart' => 'Retard de mobilisation des pieces justificatives et contraintes de calendrier.',
            'mesure_corrective' => 'Plan de rattrapage et revue mensuelle des jalons.',
        ]);
    }
}
