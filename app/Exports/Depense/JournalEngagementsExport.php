<?php

namespace App\Exports\Depense;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Journal des engagements (phase D1). */
class JournalEngagementsExport implements FromCollection, WithHeadings
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
        return ['N°', 'Exercice', 'Besoin', 'Tâche', 'Objet', 'Bénéficiaire', 'Montant', 'Statut', 'Date engagement', 'Visé le'];
    }
}
