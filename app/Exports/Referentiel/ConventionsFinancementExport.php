<?php

namespace App\Exports\Referentiel;

use App\Models\ConventionFinancement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConventionsFinancementExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $conventions)
    {
    }

    public function collection(): Collection
    {
        return $this->conventions;
    }

    public function headings(): array
    {
        return ['Code', 'Libellé', 'PTF', 'Source', 'Exercice', 'Réf. accord', 'Montant accord', 'Engagé', 'Exécuté', 'Décaissé', 'Statut'];
    }

    /** @param ConventionFinancement $c */
    public function map($c): array
    {
        return [
            $c->code,
            $c->libelle,
            $c->partenaire?->sigle ?? $c->partenaire?->libelle ?? '—',
            $c->sourceFinancement?->code ?? '—',
            $c->exercice?->annee ?? '—',
            $c->reference_accord ?? '—',
            (float) $c->montant_accord,
            (float) $c->montant_engage,
            (float) $c->montant_execute,
            (float) $c->montant_decaisse,
            ConventionFinancement::STATUTS[$c->statut] ?? $c->statut,
        ];
    }
}
