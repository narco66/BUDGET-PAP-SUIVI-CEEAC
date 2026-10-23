<?php

namespace App\Exports\Depense;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Journal consolidé des liquidations (phase D3). */
class JournalLiquidationsExport implements FromCollection, WithHeadings
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
        return ['N°', 'Engagement', 'Besoin', 'Tâche', 'Objet', 'Montant', 'Statut', 'Date', 'Validé le'];
    }
}
