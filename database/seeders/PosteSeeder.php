<?php

namespace Database\Seeders;

use App\Models\Poste;
use Illuminate\Database\Seeder;

class PosteSeeder extends Seeder
{
    public function run(): void
    {
        $postes = [
            ['DG', 'Directeur Général'],
            ['CB', 'Chef de Bureau'],
            ['GB', 'Gestionnaire Budgétaire'],
            ['CF', 'Contrôleur Financier'],
            ['AC', 'Agent Comptable'],
            ['PF', 'Point Focal'],
        ];

        foreach ($postes as [$code, $libelle]) {
            Poste::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'actif' => true]);
        }
    }
}
