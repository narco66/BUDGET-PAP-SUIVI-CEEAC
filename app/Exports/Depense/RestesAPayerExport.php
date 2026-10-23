<?php

namespace App\Exports\Depense;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Situation des restes à payer (phase D1). */
class RestesAPayerExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Collection $lignes)
    {
    }

    public function collection(): Collection
    {
        return $this->lignes;
    }

    public function headings(): array
    {
        return ['N° engagement', 'Objet', 'Structure', 'Bénéficiaire', 'Engagé', 'Liquidé', 'Ordonnancé', 'Payé', 'Reste à payer'];
    }
}
