<?php

namespace App\Exports\Rbm;

use App\Models\Exercice;
use App\Services\Rbm\RbmReferentielService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Export Excel du catalogue des indicateurs RBM (phase 4). */
class IndicateursCatalogueExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Exercice $exercice)
    {
    }

    public function collection(): Collection
    {
        return app(RbmReferentielService::class)->catalogueIndicateurs($this->exercice);
    }

    public function headings(): array
    {
        return ['Code', 'Libellé', 'Type', 'Unité', 'Périodicité', 'Cible', 'Contexte RBM'];
    }
}
