<?php

namespace App\Exports\Recettes;

use App\Models\PrevisionRecette;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PrevisionsRecettesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $previsions)
    {
    }

    public function collection(): Collection
    {
        return $this->previsions;
    }

    public function headings(): array
    {
        return ['Référence', 'Exercice', 'Catégorie', 'Origine', 'Scénario', 'Version', 'Montant prévu', 'Montant révisé', 'Montant retenu', 'Statut'];
    }

    /** @param PrevisionRecette $p */
    public function map($p): array
    {
        return [
            $p->reference,
            $p->exercice?->annee ?? '—',
            $p->categorie?->libelle ?? '—',
            $p->etatMembre?->pays ?? $p->sourceFinancement?->libelle ?? '—',
            $p->scenario,
            $p->version,
            (float) $p->montant_prevu,
            $p->montant_revise !== null ? (float) $p->montant_revise : '',
            (float) $p->montant_retenu,
            PrevisionRecette::STATUTS[$p->statut] ?? $p->statut,
        ];
    }
}
