<?php

namespace App\Exports\Strategie;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StrategieCatalogueExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly Collection $lignes,
        private readonly array $headings,
    ) {
    }

    public function collection(): Collection
    {
        return $this->lignes;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
