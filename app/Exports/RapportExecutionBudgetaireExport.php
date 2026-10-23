<?php

namespace App\Exports;

use App\Services\TableauBordService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Rapport d'exécution budgétaire par nature (Excel). */
class RapportExecutionBudgetaireExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private readonly int $exerciceId)
    {
    }

    public function title(): string
    {
        return 'Exécution budgétaire';
    }

    public function headings(): array
    {
        return ['Titre', 'Intitulé', 'Ouvert', 'Engagé', 'Payé', 'Taux engagement %', 'Taux exécution %'];
    }

    public function array(): array
    {
        $lignes = app(TableauBordService::class)->consommationParNature($this->exerciceId);

        return $lignes->map(function ($n) {
            $ouvert = (float) $n->ouvert;

            return [
                $n->code, $n->intitule,
                $ouvert, (float) $n->engage, (float) $n->paye,
                $ouvert > 0 ? round((float) $n->engage / $ouvert * 100, 1) : 0,
                $ouvert > 0 ? round((float) $n->paye / $ouvert * 100, 1) : 0,
            ];
        })->all();
    }
}
