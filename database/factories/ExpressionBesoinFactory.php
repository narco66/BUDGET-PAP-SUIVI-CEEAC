<?php

namespace Database\Factories;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpressionBesoin> */
class ExpressionBesoinFactory extends Factory
{
    protected $model = ExpressionBesoin::class;

    public function definition(): array
    {
        $ligne = LigneBudgetaire::factory()->nonConsommee()->create();
        $tache = Tache::factory(['activite_id' => $ligne->activite_id])->create();

        return [
            'numero' => 'EB-'.now()->year.'-'.fake()->unique()->numberBetween(1000, 9999),
            'exercice_id' => $ligne->exercice_id,
            'structure_id' => $ligne->structure_id ?? Structure::factory(),
            'activite_id' => $ligne->activite_id ?? Activite::factory(),
            'tache_id' => $tache->id,
            'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => User::factory(),
            'objet' => fake()->randomElement([
                'Organisation d un atelier regional de planification',
                'Mission de suivi des engagements communautaires',
                'Acquisition de fournitures pour la cellule de coordination',
                'Appui technique a la production du rapport de performance',
            ]),
            'justification' => 'Besoin de demonstration relie au PAP, au budget et a la chaine de depense.',
            'montant_estime' => min(10000000, (float) $ligne->montant_ouvert),
            'priorite' => 'moyenne',
            'date_besoin' => now()->toDateString(),
            'statut' => 'brouillon',
        ];
    }

    public function valide(): self
    {
        return $this->state(fn () => ['statut' => 'valide']);
    }
}
