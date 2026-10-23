<?php

namespace Database\Seeders;

use App\Models\Exercice;
use Illuminate\Database\Seeder;

class ExerciceSeeder extends Seeder
{
    public function run(): void
    {
        Exercice::updateOrCreate(
            ['annee' => 2026],
            [
                'code' => '2026',
                'libelle' => 'Exercice budgétaire 2026',
                'statut' => 'ouvert',
                'est_actif' => true,
                'date_debut' => '2026-01-01',
                'date_fin' => '2026-12-31',
                'devise' => 'XAF',
            ]
        );
    }
}
