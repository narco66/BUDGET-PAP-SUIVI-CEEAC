<?php

namespace Database\Factories;

use App\Models\Activite;
use App\Models\Budget;
use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LigneBudgetaire> */
class LigneBudgetaireFactory extends Factory
{
    protected $model = LigneBudgetaire::class;

    public function definition(): array
    {
        $budget = Budget::factory()->create();
        $ouvert = fake()->randomElement([25000000, 50000000, 100000000, 150000000]);
        $engage = round($ouvert * 0.45, 2);
        $liquide = round($engage * 0.7, 2);
        $ordonnance = round($liquide * 0.8, 2);
        $paye = round($ordonnance * 0.75, 2);

        return [
            'exercice_id' => $budget->exercice_id,
            'budget_id' => $budget->id,
            'structure_id' => Structure::factory(),
            'rubrique_id' => fn () => self::rubriqueDepense()->id,
            'source_financement_id' => SourceFinancement::factory(),
            'activite_id' => Activite::factory(),
            'code' => 'LB-'.fake()->unique()->numberBetween(10000, 99999),
            'intitule' => fake()->randomElement([
                'Credits de coordination institutionnelle',
                'Credits de suivi evaluation et reporting',
                'Credits d appui aux programmes sectoriels',
                'Credits de missions et ateliers regionaux',
            ]),
            'nature' => 'depense',
            'date_entree_vigueur' => now()->startOfYear()->toDateString(),
            'montant_ouvert' => $ouvert,
            'montant_demande' => round($ouvert * 1.08, 2),
            'montant_propose' => $ouvert,
            'montant_revise' => $ouvert,
            'montant_engage' => $engage,
            'montant_liquide' => $liquide,
            'montant_ordonnance' => $ordonnance,
            'montant_paye' => $paye,
            'actif' => true,
        ];
    }

    public function nonConsommee(): self
    {
        return $this->state(fn () => [
            'montant_engage' => 0,
            'montant_liquide' => 0,
            'montant_ordonnance' => 0,
            'montant_paye' => 0,
        ]);
    }

    private static function rubriqueDepense(): BudgetRubrique
    {
        $titre = BudgetTitre::firstOrCreate(
            ['code' => '6', 'sens' => 'depense'],
            ['intitule' => 'Charges de fonctionnement', 'ordre' => 6]
        );

        $article = BudgetArticle::firstOrCreate(
            ['titre_id' => $titre->id, 'code' => '61'],
            ['intitule' => 'Services exterieurs', 'ordre' => 1]
        );

        $paragraphe = BudgetParagraphe::firstOrCreate(
            ['article_id' => $article->id, 'code' => '611'],
            ['intitule' => 'Missions et prestations techniques', 'ordre' => 1]
        );

        return BudgetRubrique::firstOrCreate(
            ['paragraphe_id' => $paragraphe->id, 'code' => '6111'],
            ['intitule' => 'Appui technique et coordination', 'ordre' => 1]
        );
    }
}
