<?php

namespace App\Exports\Interop;

use App\Services\Interop\OpenApiService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Export inventaire endpoints API (I-P3). */
class ApiInventaireExport implements FromArray, WithHeadings
{
    public function __construct(private readonly OpenApiService $openApi) {}

    public function headings(): array
    {
        return ['Méthode', 'Chemin', 'OperationId', 'Tags', 'Résumé'];
    }

    public function array(): array
    {
        return collect($this->openApi->inventaireEndpoints())
            ->map(fn ($r) => [$r['methode'], $r['chemin'], $r['operationId'], $r['tags'], $r['summary']])
            ->all();
    }
}
