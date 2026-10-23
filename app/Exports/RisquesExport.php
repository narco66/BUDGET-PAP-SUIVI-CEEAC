<?php

namespace App\Exports;

use App\Models\Risque;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Export du registre des risques (Excel / CSV). */
class RisquesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Risque::with(['categorie', 'proprietaire', 'structure'])->orderByRaw('probabilite * impact DESC')->orderBy('code')->get();
    }

    public function headings(): array
    {
        return ['Code', 'Catégorie', 'Libellé', 'Processus', 'Propriétaire', 'Structure', 'Probabilité', 'Impact', 'Criticité', 'Niveau', 'Statut', 'Mesures de maîtrise'];
    }

    public function map($r): array
    {
        return [
            $r->code,
            $r->categorie?->libelle,
            $r->libelle,
            $r->processus,
            $r->proprietaire?->name,
            $r->structure?->sigle ?? $r->structure?->libelle,
            $r->probabilite,
            $r->impact,
            $r->criticite,
            Risque::niveauMeta($r->niveau)['libelle'],
            Risque::STATUTS[$r->statut] ?? $r->statut,
            $r->mesures_maitrise,
        ];
    }
}
