<?php

namespace App\Exports\Ged;

use App\Models\Ged\GedSignature;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GedSignaturesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return GedSignature::with(['document', 'signataire'])->latest()->get();
    }

    public function headings(): array
    {
        return ['Document', 'Signataire', 'Statut', 'Date signature', 'Hash document'];
    }

    public function map($s): array
    {
        return [
            $s->document?->reference,
            $s->signataire?->name,
            $s->statut,
            $s->date_signature?->format('Y-m-d H:i'),
            $s->hash_document,
        ];
    }
}
