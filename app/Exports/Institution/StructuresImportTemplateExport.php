<?php

namespace App\Exports\Institution;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Modèle Excel pour l'import structures (phase A8). */
class StructuresImportTemplateExport implements FromArray, WithHeadings
{
    /** @param  array<string, string>  $exemple */
    public function __construct(private readonly array $exemple)
    {
    }

    public function headings(): array
    {
        return array_keys($this->exemple);
    }

    public function array(): array
    {
        return [array_values($this->exemple)];
    }
}
