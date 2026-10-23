<?php

namespace App\Contracts\Ged;

/** Contrat pour analyse antivirus des fichiers déposés. */
interface GedAntivirusScannerInterface
{
    /**
     * @return array{propre: bool, menace: ?string}
     */
    public function analyser(string $cheminAbsolu): array;
}
