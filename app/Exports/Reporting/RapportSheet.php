<?php

namespace App\Exports\Reporting;

use App\Support\CharteCeeac;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Une feuille Excel construite à partir d'une section « table » normalisée (valeurs brutes numériques). */
class RapportSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly string $titre,
        private readonly array $headings,
        private readonly array $rows,
        private readonly ?array $total = null,
    ) {
    }

    public function array(): array
    {
        $rows = $this->rows;
        if ($this->total) {
            $rows[] = $this->total;
        }

        return $rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        $t = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $this->titre);

        return mb_substr(trim($t), 0, 31) ?: 'Feuille';
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => CharteCeeac::primaireHex()]],
            ],
        ];

        if ($this->total) {
            $derniere = count($this->rows) + 2;
            $styles[$derniere] = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => CharteCeeac::fondTableauHex()]],
            ];
        }

        return $styles;
    }
}
