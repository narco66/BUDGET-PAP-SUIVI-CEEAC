<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Modèle d'import du détail PAP (en-têtes + lignes d'exemple). */
class ModelePapExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'pilier_code', 'pilier_intitule', 'axe_code', 'axe_intitule',
            'produit_code', 'produit_intitule', 'sous_produit_code', 'sous_produit_intitule',
            'activite_code', 'activite_intitule', 'montant_ceeac', 'montant_ptf', 'ligne_code',
        ];
    }

    public function array(): array
    {
        return [
            ['201', 'Intégration politique, paix et sécurité', '2011', 'Renforcer la gouvernance politique',
                '20111', 'Fonctionnement du Comité des Sages', '201110', 'Comité des Sages',
                '2011100', 'Fonctionnement du Comité des Sages', 150000000, 0, '2011100'],
            ['201', 'Intégration politique, paix et sécurité', '2011', 'Renforcer la gouvernance politique',
                '20112', 'Mécanismes communautaires de médiation', '201121', 'Cadre régional de médiation',
                '2011210', 'Élaboration et mise en œuvre du cadre régional de médiation', 135000000, 0, '2011210'],
        ];
    }
}
