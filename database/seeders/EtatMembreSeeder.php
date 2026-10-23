<?php

namespace Database\Seeders;

use App\Models\Contribution;
use App\Models\Exercice;
use App\Models\EtatMembre;
use App\Models\SourceFinancement;
use Illuminate\Database\Seeder;

/**
 * 11 États membres + contributions statutaires CCI appelées pour 2026
 * (colonne « Recettes attendues 2026 » du Budget exercice 2026).
 */
class EtatMembreSeeder extends Seeder
{
    public function run(): void
    {
        // [code nomenclature, pays, montant appelé 2026 (XAF)]
        $etats = [
            ['721010', "République d'Angola", 2_890_306_628],
            ['721020', 'République du Burundi', 1_313_775_740],
            ['721030', 'République du Cameroun', 2_890_306_628],
            ['721040', 'République Centrafricaine', 1_313_775_740],
            ['721050', 'République du Congo', 2_890_306_628],
            ['721060', 'République Démocratique du Congo', 2_627_551_480],
            ['721070', 'République Gabonaise', 2_890_306_628],
            ['721080', 'République de Guinée Équatoriale', 2_890_306_628],
            ['721090', 'République Rwandaise', 2_627_551_480],
            ['721100', 'République Démocratique de Sao Tomé-et-Principe', 1_313_775_740],
            ['721110', 'République du Tchad', 2_627_551_480],
        ];

        $exercice = Exercice::firstWhere('annee', 2026);
        $source = SourceFinancement::firstWhere('code', 'CEEAC-EM');
        $total = array_sum(array_column($etats, 2));

        foreach ($etats as [$code, $pays, $montant]) {
            $etat = EtatMembre::updateOrCreate(
                ['code' => $code],
                [
                    'pays' => $pays,
                    'cle_repartition' => round($montant / $total * 100, 4),
                    'actif' => true,
                ]
            );

            if ($exercice) {
                Contribution::updateOrCreate(
                    ['exercice_id' => $exercice->id, 'etat_membre_id' => $etat->id],
                    [
                        'source_financement_id' => $source?->id,
                        'montant_appele' => $montant,
                        'montant_realise' => 0,
                    ]
                );
            }
        }
    }
}
