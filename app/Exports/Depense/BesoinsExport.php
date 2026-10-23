<?php

namespace App\Exports\Depense;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Export des expressions de besoin (phase D1). */
class BesoinsExport implements FromCollection, WithHeadings
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
        return ['N°', 'Exercice', 'Structure', 'Activité', 'Tâche', 'Priorité', 'Objet', 'Montant estimé', 'Statut', 'Date'];
    }
}
