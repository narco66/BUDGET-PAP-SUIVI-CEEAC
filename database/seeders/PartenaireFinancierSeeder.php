<?php

namespace Database\Seeders;

use App\Models\PartenaireFinancier;
use App\Models\SourceFinancement;
use Illuminate\Database\Seeder;

/** Rattache les sources existantes aux PTF structurés (Phase B, non destructif). */
class PartenaireFinancierSeeder extends Seeder
{
    public function run(): void
    {
        $ptf = [
            ['code' => 'BAD', 'libelle' => 'Banque Africaine de Développement', 'sigle' => 'BAD', 'type' => 'multilateral', 'sources' => ['BAD']],
            ['code' => 'UE', 'libelle' => 'Union Européenne', 'sigle' => 'UE', 'type' => 'bilateral', 'sources' => ['UE']],
            ['code' => 'BM', 'libelle' => 'Banque Mondiale', 'sigle' => 'BM', 'type' => 'multilateral', 'sources' => ['BM']],
            ['code' => 'AU', 'libelle' => 'Union Africaine / AUDA-NEPAD', 'sigle' => 'AU', 'type' => 'multilateral', 'sources' => ['AU-AUDA']],
            ['code' => 'ONUDI', 'libelle' => 'Organisation des Nations Unies pour le Développement Industriel', 'sigle' => 'ONUDI', 'type' => 'onu', 'sources' => ['ONUDI']],
            ['code' => 'ONU', 'libelle' => 'Système des Nations Unies', 'sigle' => 'ONU', 'type' => 'onu', 'sources' => ['ONU-SI']],
            ['code' => 'PTF-DIV', 'libelle' => 'Partenaires techniques et financiers divers', 'sigle' => 'PTF', 'type' => 'autre', 'sources' => ['PTF-DIVERS']],
        ];

        foreach ($ptf as $row) {
            $partenaire = PartenaireFinancier::updateOrCreate(
                ['code' => $row['code']],
                ['libelle' => $row['libelle'], 'sigle' => $row['sigle'], 'type' => $row['type'], 'actif' => true]
            );

            foreach ($row['sources'] as $sourceCode) {
                SourceFinancement::where('code', $sourceCode)->update([
                    'partenaire_id' => $partenaire->id,
                    'classification' => 'cooperation',
                ]);
            }
        }

        SourceFinancement::where('code', 'CEEAC-EM')->update([
            'classification' => 'contribution_statutaire',
        ]);
    }
}
