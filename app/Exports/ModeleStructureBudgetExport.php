<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Modèle d'import structure budgétaire (colonnes enrichies PAP + nomenclature composite). */
class ModeleStructureBudgetExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'code', 'intitule', 'structure_code', 'activite_code',
            'rubrique_code', 'paragraphe_code', 'article_code', 'titre_code',
            'source_code', 'montant_ouvert',
        ];
    }

    public function array(): array
    {
        return [
            [
                'LB-2026-0001', 'Traitements et salaires', 'DIR-BUDGET', 'ACT-001',
                '661000', '6610', '66', '6',
                'CEEAC-EM', 6499720906,
            ],
            [
                'LB-2026-0002', 'Fournitures de bureau', 'SG', '',
                '601100', '6011', '60', '6',
                'CEEAC-EM', 50000000,
            ],
        ];
    }
}
