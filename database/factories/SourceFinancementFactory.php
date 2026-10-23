<?php

namespace Database\Factories;

use App\Models\SourceFinancement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SourceFinancement> */
class SourceFinancementFactory extends Factory
{
    protected $model = SourceFinancement::class;

    public function definition(): array
    {
        return [
            'code' => 'SRC-'.fake()->unique()->numberBetween(100, 999),
            'libelle' => fake()->randomElement([
                'Contribution communautaire d integration',
                'Appui technique a l integration regionale',
                'Fonds de cooperation pour la gouvernance regionale',
            ]),
            'type' => 'interne_ceeac',
            'classification' => 'fonds_communautaires',
            'bailleur' => null,
            'description' => 'Source de financement de demonstration CEEAC.',
            'actif' => true,
        ];
    }

    public function ptf(string $bailleur = 'Partenaire technique et financier'): self
    {
        return $this->state(fn () => [
            'type' => 'externe_ptf',
            'classification' => 'cooperation',
            'bailleur' => $bailleur,
        ]);
    }
}
