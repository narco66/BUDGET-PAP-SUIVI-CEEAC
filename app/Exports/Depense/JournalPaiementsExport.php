<?php

namespace App\Exports\Depense;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Journal des paiements (phase D1). */
class JournalPaiementsExport implements FromCollection, WithHeadings
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
        return ['N° paiement', 'N° engagement', 'Objet', 'Montant payé', 'Mode', 'Réf. bancaire', 'Statut', 'Date paiement'];
    }
}
