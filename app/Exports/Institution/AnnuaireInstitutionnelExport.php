<?php

namespace App\Exports\Institution;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AnnuaireInstitutionnelExport implements FromCollection, WithHeadings, WithMapping
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
        return [
            'Code',
            'Libelle',
            'Sigle',
            'Type',
            'Parent (code)',
            'Parent (libelle)',
            'Departement',
            'Direction',
            'Service',
            'Responsable',
            'Email responsable',
            'Structure officielle',
            'Niveau',
            'Actif',
        ];
    }

    /** @param  array<string, mixed>  $ligne */
    public function map($ligne): array
    {
        return [
            $ligne['code'],
            $ligne['libelle'],
            $ligne['sigle'],
            $ligne['type'],
            $ligne['parent_code'],
            $ligne['parent_libelle'],
            $ligne['departement'],
            $ligne['direction'],
            $ligne['service'],
            $ligne['responsable'],
            $ligne['responsable_email'],
            $ligne['structure_officielle'],
            $ligne['niveau'],
            $ligne['actif'],
        ];
    }
}
