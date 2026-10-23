<?php

namespace App\Services\Ged\Antivirus;

use App\Contracts\Ged\GedAntivirusScannerInterface;

/** Scanner de test (P3-9) — détecte la chaîne EICAR dans le contenu. */
class TestAntivirusScanner implements GedAntivirusScannerInterface
{
    public const MARQUEUR = 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE';

    public function analyser(string $cheminAbsolu): array
    {
        if (! is_readable($cheminAbsolu)) {
            return ['propre' => false, 'menace' => 'fichier_introuvable'];
        }

        $contenu = (string) file_get_contents($cheminAbsolu);
        if (str_contains($contenu, self::MARQUEUR)) {
            return ['propre' => false, 'menace' => 'EICAR-Test-File'];
        }

        return ['propre' => true, 'menace' => null];
    }
}
