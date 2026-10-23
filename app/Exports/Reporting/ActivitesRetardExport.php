<?php

namespace App\Exports\Reporting;

use App\Models\Activite;
use App\Services\Reporting\ActivitesRetardService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ActivitesRetardExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Collection $activites,
        private readonly ActivitesRetardService $svc,
    ) {
    }

    public function collection(): Collection
    {
        return $this->activites;
    }

    public function headings(): array
    {
        return [
            'Code', 'Activité', 'Pilier', 'Axe', 'Produit', 'Sous-produit',
            'Département', 'Responsable', 'Date début', 'Date fin prévue',
            'Jours de retard', 'Avancement physique (%)', 'Avancement financier (%)',
            'Budget alloué', 'Budget consommé', 'Statut',
        ];
    }

    /** @param Activite $a */
    public function map($a): array
    {
        return [
            $a->code,
            $a->intitule,
            $a->produit?->axe?->pilier?->code ?? '—',
            $a->produit?->axe?->code ?? '—',
            $a->produit?->code ?? '—',
            $a->sousProduit?->code ?? '—',
            $a->structure?->libelle ?? '—',
            $a->responsable?->name ?? '—',
            $a->date_debut_prevue ? \Illuminate\Support\Carbon::parse($a->date_debut_prevue)->format('d/m/Y') : '—',
            $a->date_fin_prevue ? \Illuminate\Support\Carbon::parse($a->date_fin_prevue)->format('d/m/Y') : '—',
            $this->svc->joursRetard($a),
            (float) $a->taux_realisation_physique,
            (float) $a->taux_realisation_financier,
            (float) $a->montant_total,
            $this->svc->budgetConsomme($a),
            $a->statut,
        ];
    }
}
