<?php

namespace App\Exports\Rbm;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Modèle d'import des indicateurs RBM (phase 3). */
class ModeleIndicateursExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'pilier_code', 'axe_code', 'produit_code', 'sous_produit_code',
            'niveau', 'noeud_code',
            'indicateur_code', 'libelle', 'type', 'unite', 'periodicite',
            'valeur_reference', 'valeur_cible', 'seuil_alerte',
            'methode_calcul', 'source_verification',
        ];
    }

    public function array(): array
    {
        return [[
            '201', '2011', '', '',
            'pilier', '201',
            'IND-201', 'Taux de mise en œuvre pilier 201', 'strategique', '%', 'annuel',
            0, 100, 50,
            'Activités réalisées / planifiées', 'Rapport trimestriel',
        ]];
    }
}
