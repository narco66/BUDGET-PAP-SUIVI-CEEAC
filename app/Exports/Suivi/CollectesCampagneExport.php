<?php

namespace App\Exports\Suivi;

use App\Models\Collecte;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CollectesCampagneExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $collectes)
    {
    }

    public function collection(): Collection
    {
        return $this->collectes;
    }

    public function headings(): array
    {
        return [
            'Période', 'Activité', 'Structure', 'Taux phys. (%)', 'Qté prévue', 'Qté réalisée', 'Unité',
            'Montant réalisé', 'Taux fin. (%)', 'Écart', 'Statut', 'Date réalisation',
        ];
    }

    /** @param Collecte $c */
    public function map($c): array
    {
        return [
            $c->campagne?->periode,
            $c->activite?->code,
            $c->activite?->structure?->sigle ?? '—',
            (float) $c->taux_physique,
            $c->quantite_prevue,
            $c->quantite_realisee,
            $c->unite,
            (float) $c->montant_realise,
            (float) $c->taux_financier,
            (float) $c->ecart,
            $c->statut,
            $c->date_realisation?->format('d/m/Y'),
        ];
    }
}
