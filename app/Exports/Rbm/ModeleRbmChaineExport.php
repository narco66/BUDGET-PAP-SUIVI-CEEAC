<?php

namespace App\Exports\Rbm;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Modèle d'import chaîne RBM/GAR (phase 2 — avec tâches optionnelles). */
class ModeleRbmChaineExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'pilier_code', 'pilier_intitule', 'axe_code', 'axe_intitule',
            'produit_code', 'produit_intitule', 'sous_produit_code', 'sous_produit_intitule',
            'activite_code', 'activite_intitule', 'montant_ceeac', 'montant_ptf',
            'tache_code', 'tache_intitule', 'tache_montant', 'tache_statut',
        ];
    }

    public function array(): array
    {
        return [
            [
                '201', 'Intégration politique, paix et sécurité', '2011', 'Renforcer la gouvernance',
                '20111', 'Fonctionnement institutionnel', '201110', 'Comité des Sages',
                '2011100', 'Fonctionnement du Comité des Sages', 150000000, 0,
                'T001', 'Session ordinaire', 50000000, 'en_cours',
            ],
        ];
    }
}
