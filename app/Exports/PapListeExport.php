<?php

namespace App\Exports;

use App\Models\Pap;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Export Excel de la liste des enveloppes PAP (phase P5). */
class PapListeExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Pap::with(['exercice', 'structure', 'responsable', 'workflowActif'])
            ->orderByDesc('exercice_id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Exercice', 'Référence', 'Libellé', 'Structure', 'Responsable',
            'Statut', 'Version', 'Publié le', 'Étape workflow',
        ];
    }

    /** @param  Pap  $pap */
    public function map($pap): array
    {
        $etape = '—';
        if ($pap->workflowActif && $pap->workflowActif->statut === 'en_cours') {
            $etape = (string) $pap->workflowActif->etape_courante;
        }

        return [
            $pap->exercice?->annee,
            $pap->reference,
            $pap->libelle,
            $pap->structure?->libelle,
            $pap->responsable?->name,
            $pap->statut,
            $pap->version,
            $pap->publie_le?->format('Y-m-d H:i'),
            $etape,
        ];
    }
}
