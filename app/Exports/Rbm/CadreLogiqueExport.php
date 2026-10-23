<?php

namespace App\Exports\Rbm;

use App\Models\Exercice;
use App\Services\Rbm\RbmReferentielService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CadreLogiqueExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Exercice $exercice)
    {
    }

    public function collection(): Collection
    {
        return app(RbmReferentielService::class)->cadreLogiqueLignesExport($this->exercice);
    }

    public function headings(): array
    {
        return [
            'Pilier',
            'Intitulé pilier',
            'Axe',
            'Intitulé axe',
            'Objectif stratégique',
            'Commentaire',
            'Indicateurs IOV',
        ];
    }
}
