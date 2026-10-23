<?php

namespace App\Exports\Rbm;

use App\Models\Exercice;
use App\Services\Rbm\RbmReferentielService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MatriceRbmExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Exercice $exercice)
    {
    }

    public function collection()
    {
        return app(RbmReferentielService::class)->matriceRbm($this->exercice);
    }

    public function headings(): array
    {
        return ['Pilier', 'Axe', 'Produit', 'Sous-produit', 'Activité', 'Intitulé', 'Montant', 'Nb tâches', 'Nb indicateurs'];
    }
}
