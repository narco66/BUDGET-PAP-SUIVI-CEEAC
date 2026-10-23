<?php

namespace App\Exports\Ged;

use App\Models\Ged\GedArchive;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GedArchivesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return GedArchive::with(['document', 'archiveur'])->latest('date_archivage')->get();
    }

    public function headings(): array
    {
        return ['Document', 'Motif', 'Type', 'Date archivage', 'Archivé par', 'Restauré'];
    }

    public function map($a): array
    {
        return [
            $a->document?->reference,
            $a->motif,
            $a->type_archivage,
            $a->date_archivage?->format('Y-m-d H:i'),
            $a->archiveur?->name,
            $a->est_restaure ? 'Oui' : 'Non',
        ];
    }
}
