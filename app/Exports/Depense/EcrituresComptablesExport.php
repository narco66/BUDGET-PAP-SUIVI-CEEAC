<?php

namespace App\Exports\Depense;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Export des écritures comptables SYSCOHADA (phase D4). */
class EcrituresComptablesExport implements FromCollection, WithHeadings
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
        return ['N° pièce', 'Journal', 'Date', 'Débit', 'Crédit', 'Montant XAF', 'Paiement', 'Engagement', 'Libellé', 'Statut'];
    }
}
