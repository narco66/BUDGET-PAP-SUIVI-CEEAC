<?php

namespace App\Exports;

use App\Models\LigneBudgetaire;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LignesBudgetairesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly int $exerciceId)
    {
    }

    public function collection()
    {
        return LigneBudgetaire::with('rubrique', 'structure', 'sourceFinancement')
            ->where('exercice_id', $this->exerciceId)
            ->orderBy('code')->get();
    }

    public function headings(): array
    {
        return ['Code', 'Intitulé', 'Structure', 'Rubrique', 'Source', 'Ouvert', 'Engagé', 'Liquidé', 'Ordonnancé', 'Payé', 'Disponible'];
    }

    public function map($ligne): array
    {
        return [
            $ligne->code,
            $ligne->intitule,
            $ligne->structure?->sigle ?? $ligne->structure?->libelle,
            $ligne->rubrique?->code,
            $ligne->sourceFinancement?->code,
            (float) $ligne->montant_ouvert,
            (float) $ligne->montant_engage,
            (float) $ligne->montant_liquide,
            (float) $ligne->montant_ordonnance,
            (float) $ligne->montant_paye,
            $ligne->disponible,
        ];
    }
}
