<?php

namespace Database\Seeders;

use App\Models\Exercice;
use App\Services\Recettes\GestionRecettesService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Phase R1 — catégories de recettes et reprise des prévisions depuis `contributions`.
 * Idempotent : peut être relancé sans doublon (clé `contribution_id`).
 */
class GestionRecettesSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(GestionRecettesService::class);
        $service->assurerCategories();

        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            throw new RuntimeException('Exercice 2026 absent — lancer ExerciceSeeder avant GestionRecettesSeeder.');
        }

        $compteurs = $service->synchroniserDepuisContributions($exercice->id);

        if ($this->command) {
            $this->command->info(sprintf(
                'GestionRecettesSeeder : %d prévision(s), %d recette(s) synchronisée(s) pour 2026.',
                $compteurs['previsions'],
                $compteurs['recettes']
            ));
        }
    }
}
