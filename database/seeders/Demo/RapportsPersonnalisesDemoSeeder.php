<?php

namespace Database\Seeders\Demo;

use App\Models\RapportPersonnalise;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Modèles de rapports personnalisés de démonstration (P3-1). */
class RapportsPersonnalisesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstWhere('email', 'administrateur@ceeac.test');
        if (! $admin) {
            return;
        }

        RapportPersonnalise::firstOrCreate(
            ['code' => 'RPT-PERS-001'],
            [
                'titre' => 'Synthèse exécution par ligne budgétaire',
                'description' => 'Export personnalisé : crédits ouverts, engagés et payés par ligne.',
                'source' => 'lignes_budgetaires',
                'colonnes' => ['code', 'intitule', 'structure', 'ouvert', 'engage', 'paye', 'taux_paye'],
                'cree_par' => $admin->id,
                'partage' => true,
                'actif' => true,
            ]
        );
    }
}
