<?php

namespace App\Exports\Referentiel;

use App\Models\SourceFinancement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SourcesFinancementExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $sources)
    {
    }

    public function collection(): Collection
    {
        return $this->sources;
    }

    public function headings(): array
    {
        return ['Code', 'Libellé', 'Type', 'Classification', 'PTF', 'Bailleur', 'Actif'];
    }

    /** @param SourceFinancement $source */
    public function map($source): array
    {
        return [
            $source->code,
            $source->libelle,
            SourceFinancement::TYPES[$source->type] ?? $source->type,
            $source->classification ? (SourceFinancement::CLASSIFICATIONS[$source->classification] ?? $source->classification) : '—',
            $source->partenaire?->sigle ?? $source->partenaire?->libelle ?? '—',
            $source->bailleur ?? '—',
            $source->actif ? 'Oui' : 'Non',
        ];
    }
}
