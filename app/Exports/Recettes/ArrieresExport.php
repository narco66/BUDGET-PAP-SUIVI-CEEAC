<?php

namespace App\Exports\Recettes;

use App\Models\AppelFonds;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ArrieresExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $appels)
    {
    }

    public function collection(): Collection
    {
        return $this->appels;
    }

    public function headings(): array
    {
        return ['Référence', 'Exercice', 'Débiteur', 'Objet', 'Échéance', 'Appelé', 'Recouvré', 'Arriéré', 'Pénalité', 'Statut'];
    }

    /** @param AppelFonds $a */
    public function map($a): array
    {
        return [
            $a->reference,
            $a->exercice?->annee ?? '—',
            $a->etatMembre?->pays ?? $a->sourceFinancement?->libelle ?? '—',
            $a->objet,
            $a->date_echeance?->format('d/m/Y') ?? '—',
            (float) $a->montant_appele,
            (float) $a->montant_regle,
            (float) $a->solde,
            (float) $a->penalite,
            AppelFonds::STATUTS[$a->statut] ?? $a->statut,
        ];
    }
}
