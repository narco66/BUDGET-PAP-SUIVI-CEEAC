<?php

namespace App\Services\Ged\Antivirus;

use App\Contracts\Ged\GedAntivirusScannerInterface;

/** Scanner neutre — aucune analyse (développement / antivirus désactivé). */
class NullAntivirusScanner implements GedAntivirusScannerInterface
{
    public function analyser(string $cheminAbsolu): array
    {
        return ['propre' => true, 'menace' => null];
    }
}
