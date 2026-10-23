<?php

namespace App\Exports\Ged;

use App\Models\Ged\GedJournal;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GedJournalExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return GedJournal::with(['document', 'utilisateur'])->latest()->limit(5000)->get();
    }

    public function headings(): array
    {
        return ['Date', 'Action', 'Document', 'Utilisateur', 'IP', 'Commentaire'];
    }

    public function map($j): array
    {
        return [
            $j->created_at->format('Y-m-d H:i:s'),
            $j->action,
            $j->document?->reference,
            $j->utilisateur?->name,
            $j->adresse_ip,
            $j->commentaire,
        ];
    }
}
