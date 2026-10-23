<?php

namespace Database\Seeders;

use App\Models\Contribution;
use App\Models\Exercice;
use App\Models\SourceFinancement;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Reprise des recettes externes 2026 (dons des institutions internationales / PTF par bailleur).
 * Les contributions statutaires internes (11 États) sont seedées par EtatMembreSeeder.
 * Total dons projets attendu : 14 030 081 000 XAF (contrôle interne).
 */
class RecetteSeeder extends Seeder
{
    private const TOTAL_DONS = 14_030_081_000;

    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            throw new RuntimeException('Exercice 2026 absent.');
        }

        // [code source, libellé, montant appelé 2026]
        $bailleurs = [
            ['BAD', 'Banque Africaine de Développement', 9_355_786_000],
            ['UE', 'Union Européenne (ITC, ONUDI)', 1_280_000_000],
            ['AU-AUDA', 'Union Africaine / AUDA-NEPAD / AU-BIRA', 755_000_000],
            ['BM', 'Banque Mondiale', 750_000_000],
            ['ONUDI', 'ONUDI', 715_275_000],
            ['ONU-SI', 'Système des Nations Unies (UNOCA, UNOP, ONUSIDA, ONU-Habitat…)', 629_020_000],
            ['CONSORTIUM-PTF', 'Consortium PTF', 195_000_000],
            ['AGRA', 'AGRA', 115_000_000],
            ['CDC-AFRIQUE', 'CDC Afrique', 100_000_000],
            ['FAO', 'FAO', 85_000_000],
            ['CARD', 'CARD', 50_000_000],
        ];

        $total = 0;
        foreach ($bailleurs as [$code, $libelle, $montant]) {
            $source = SourceFinancement::updateOrCreate(
                ['code' => $code],
                ['libelle' => $libelle, 'type' => 'externe_ptf', 'bailleur' => $libelle, 'actif' => true]
            );

            Contribution::updateOrCreate(
                ['exercice_id' => $exercice->id, 'source_financement_id' => $source->id, 'etat_membre_id' => null],
                ['montant_appele' => $montant, 'montant_realise' => 0]
            );
            $total += $montant;
        }

        if ($total !== self::TOTAL_DONS) {
            throw new RuntimeException(sprintf(
                'Contrôle recettes externes échoué : %s ≠ %s.',
                number_format($total, 0, ',', ' '), number_format(self::TOTAL_DONS, 0, ',', ' ')
            ));
        }
    }
}
