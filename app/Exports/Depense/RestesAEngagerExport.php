<?php

namespace App\Exports\Depense;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Restes à engager par ligne budgétaire (phase P1). */
class RestesAEngagerExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Collection $lignes)
    {
    }

    public function collection(): Collection
    {
        return $this->lignes->map(fn ($r) => [
            $r['code'],
            $r['intitule'],
            $r['structure'],
            $r['alloue'],
            $r['engage'],
            $r['reste_a_engager'],
            $r['taux_engagement'],
        ]);
    }

    public function headings(): array
    {
        return ['Code ligne', 'Intitulé', 'Structure', 'Budget adopté', 'Engagé', 'Reste à engager', 'Taux engagement %'];
    }
}
