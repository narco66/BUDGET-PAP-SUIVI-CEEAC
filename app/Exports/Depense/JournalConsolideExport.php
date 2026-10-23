<?php

namespace App\Exports\Depense;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Journal consolidé L/O/P (phase D3). */
class JournalConsolideExport implements FromCollection, WithHeadings
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
        return ['Étape', 'N°', 'Engagement', 'Parent', 'Tâche', 'Montant', 'Statut', 'Date', 'Validé le'];
    }
}
