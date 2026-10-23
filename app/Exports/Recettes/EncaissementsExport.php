<?php

namespace App\Exports\Recettes;

use App\Models\Encaissement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EncaissementsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $encaissements)
    {
    }

    public function collection(): Collection
    {
        return $this->encaissements;
    }

    public function headings(): array
    {
        return ['Référence', 'Date', 'Recette', 'Catégorie', 'Mode', 'Montant', 'Devise', 'Taux', 'Contre-valeur XAF', 'Statut', 'Validé par'];
    }

    /** @param Encaissement $e */
    public function map($e): array
    {
        return [
            $e->reference,
            $e->date_encaissement?->format('d/m/Y'),
            $e->recette?->reference ?? '—',
            $e->recette?->categorie?->libelle ?? '—',
            Encaissement::MODES_PAIEMENT[$e->mode_paiement] ?? $e->mode_paiement,
            (float) $e->montant,
            $e->devise,
            (float) $e->taux_change,
            (float) $e->montant_xaf,
            Encaissement::STATUTS[$e->statut] ?? $e->statut,
            $e->validePar?->name ?? '—',
        ];
    }
}
