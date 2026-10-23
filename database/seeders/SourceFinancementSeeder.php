<?php

namespace Database\Seeders;

use App\Models\SourceFinancement;
use Illuminate\Database\Seeder;

class SourceFinancementSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['code' => 'CEEAC-EM', 'libelle' => 'Ressources internes CEEAC (contributions États membres)', 'type' => 'interne_ceeac', 'bailleur' => null],
            ['code' => 'BAD', 'libelle' => 'Banque Africaine de Développement', 'type' => 'externe_ptf', 'bailleur' => 'BAD'],
            ['code' => 'UE', 'libelle' => 'Union Européenne (ITC, ONUDI)', 'type' => 'externe_ptf', 'bailleur' => 'Union Européenne'],
            ['code' => 'AU-AUDA', 'libelle' => 'AU / AUDA-NEPAD / AU-BIRA', 'type' => 'externe_ptf', 'bailleur' => 'Union Africaine'],
            ['code' => 'BM', 'libelle' => 'Banque Mondiale', 'type' => 'externe_ptf', 'bailleur' => 'Banque Mondiale'],
            ['code' => 'ONUDI', 'libelle' => 'ONUDI', 'type' => 'externe_ptf', 'bailleur' => 'ONUDI'],
            ['code' => 'ONU-SI', 'libelle' => 'ONU/UNOCA/UNOP/ONUSIDA/ONU-Habitat', 'type' => 'externe_ptf', 'bailleur' => 'Système des Nations Unies'],
            ['code' => 'PTF-DIVERS', 'libelle' => 'Consortium PTF / AGRA / CDC Afrique / FAO / CARD / Autres', 'type' => 'externe_ptf', 'bailleur' => 'Partenaires divers'],
        ];

        foreach ($sources as $s) {
            SourceFinancement::updateOrCreate(['code' => $s['code']], $s);
        }
    }
}
