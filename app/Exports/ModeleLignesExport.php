<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Modèle d'import des lignes budgétaires (en-têtes + lignes d'exemple). */
class ModeleLignesExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['code', 'intitule', 'structure_code', 'rubrique_code', 'paragraphe_code', 'article_code', 'source_code', 'montant_ouvert'];
    }

    public function array(): array
    {
        return [
            ['LB-2026-0001', 'Traitements et salaires de base', 'SG', '661000', '6610', '66', 'CEEAC-EM', 6499720906],
            ['LB-2026-0002', 'Fournitures de bureau', 'DIR-BUDGET', '601100', '6011', '60', 'CEEAC-EM', 50000000],
        ];
    }
}
