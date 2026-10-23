<?php

namespace App\Exports\Reporting;

use App\Support\CharteCeeac;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Feuille « Informations » avec bannière institutionnelle CEEAC. */
class RapportInfoSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly array $composed,
        private readonly array $meta,
    ) {}

    public function array(): array
    {
        return [
            [CharteCeeac::institution()],
            [CharteCeeac::sousTitre()],
            [CharteCeeac::systeme()],
            [],
            ['Champ', 'Valeur'],
            ['Titre', $this->composed['titre']],
            ['Sous-titre', $this->composed['sous_titre']],
            ['Référence', $this->meta['reference']],
            ['Version', $this->meta['version']],
            ['Généré le', $this->meta['date']],
            ['Généré par', $this->meta['auteur']],
            ['Vérification', $this->meta['verification']],
        ];
    }

    public function title(): string
    {
        return 'Informations';
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:B1');
        $sheet->mergeCells('A2:B2');
        $sheet->mergeCells('A3:B3');

        return [
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => CharteCeeac::primaireHex()]]],
            2 => ['font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '666666']]],
            3 => ['font' => ['size' => 9, 'color' => ['rgb' => CharteCeeac::fonceHex()]]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => CharteCeeac::primaireHex()]],
            ],
        ];
    }
}
