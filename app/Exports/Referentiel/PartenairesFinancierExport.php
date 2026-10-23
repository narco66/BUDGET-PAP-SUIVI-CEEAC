<?php

namespace App\Exports\Referentiel;

use App\Models\PartenaireFinancier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PartenairesFinancierExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $partenaires)
    {
    }

    public function collection(): Collection
    {
        return $this->partenaires;
    }

    public function headings(): array
    {
        return ['Code', 'Libellé', 'Sigle', 'Type', 'Pays', 'Sources', 'Conventions', 'Actif'];
    }

    /** @param PartenaireFinancier $p */
    public function map($p): array
    {
        return [
            $p->code,
            $p->libelle,
            $p->sigle ?? '—',
            PartenaireFinancier::TYPES[$p->type] ?? $p->type,
            $p->pays ?? '—',
            $p->sources_count ?? 0,
            $p->conventions_count ?? 0,
            $p->actif ? 'Oui' : 'Non',
        ];
    }
}
