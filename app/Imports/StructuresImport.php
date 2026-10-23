<?php

namespace App\Imports;

use App\Services\Institution\InstitutionImportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import Excel du référentiel structures (phase A8).
 * En-têtes : code, libelle, sigle, type, parent_code, responsable_email, description, actif, ordre.
 */
class StructuresImport implements ToCollection, WithHeadingRow
{
    use Importable;

    /** @var array{crees:int,mis_a_jour:int,ignorees:int,erreurs:list<string>} */
    public array $stats = ['crees' => 0, 'mis_a_jour' => 0, 'ignorees' => 0, 'erreurs' => []];

    public function __construct(private readonly InstitutionImportService $service)
    {
    }

    public function collection(Collection $rows): void
    {
        $this->stats = $this->service->importerStructures($rows);
    }
}
