<?php

namespace App\Exports;

use App\Services\Budget\StructureBudgetService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class StructureBudgetExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private readonly int $exerciceId,
        private readonly StructureBudgetService $service,
        private readonly array $filtres = [],
    ) {
    }

    public function collection()
    {
        return collect($this->service->rechercher($this->exerciceId, $this->filtres, sansPagination: true));
    }

    public function headings(): array
    {
        return [
            'Code', 'Intitulé', 'Structure', 'Rubrique', 'Pilier', 'Activité PAP',
            'Projet', 'Source', 'Ouvert', 'Engagé', 'Payé', 'Disponible',
        ];
    }

    public function map($ligne): array
    {
        return [
            $ligne->code,
            $ligne->intitule,
            $ligne->structure_libelle,
            $ligne->rubrique_code,
            $ligne->pilier_intitule,
            $ligne->activite_intitule,
            $ligne->projet_intitule,
            $ligne->source_libelle,
            (float) $ligne->montant_ouvert,
            (float) $ligne->montant_engage,
            (float) $ligne->montant_paye,
            max(0, (float) $ligne->montant_ouvert - (float) $ligne->montant_engage),
        ];
    }

    public function title(): string
    {
        return 'Structure budget';
    }
}
