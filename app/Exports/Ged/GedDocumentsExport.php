<?php

namespace App\Exports\Ged;

use App\Models\Ged\GedDocument;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GedDocumentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly ?array $filtres = null) {}

    public function collection()
    {
        $q = GedDocument::with(['categorie', 'typeDocument', 'createur', 'structure']);

        if (! empty($this->filtres['est_archive'])) {
            $q->where('est_archive', true);
        }

        return $q->orderByDesc('date_depot')->get();
    }

    public function headings(): array
    {
        return ['Référence', 'Titre', 'Statut', 'Confidentialité', 'Catégorie', 'Type', 'Module', 'Auteur', 'Dépôt', 'Taille (Ko)', 'SHA-256'];
    }

    public function map($doc): array
    {
        return [
            $doc->reference,
            $doc->titre,
            $doc->statut?->value,
            $doc->niveau_confidentialite?->value,
            $doc->categorie?->libelle,
            $doc->typeDocument?->libelle,
            $doc->module_source,
            $doc->createur?->name,
            $doc->date_depot?->format('Y-m-d H:i'),
            round($doc->taille / 1024, 0),
            $doc->hash_sha256,
        ];
    }
}
