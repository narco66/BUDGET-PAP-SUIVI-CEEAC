<?php

namespace App\Exports\Recettes;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MobilisationEtatsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Code', 'État membre', 'Clé répartition (%)', 'Montant appelé', 'Recouvré', 'Arriéré', 'Taux recouvrement (%)'];
    }

    /** @param object $r */
    public function map($r): array
    {
        return [$r->code, $r->pays, $r->cle_repartition, $r->appele, $r->recouvre, $r->arriere, $r->taux];
    }
}
