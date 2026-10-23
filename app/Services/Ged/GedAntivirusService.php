<?php

namespace App\Services\Ged;

use App\Contracts\Ged\GedAntivirusScannerInterface;
use App\Services\Ged\Antivirus\ClamAvScanner;
use App\Services\Ged\Antivirus\NullAntivirusScanner;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Analyse antivirus des dépôts GED (P3-9 — ClamAV / test). */
class GedAntivirusService
{
    public function __construct(private readonly GedAntivirusScannerInterface $scanner) {}

    /** @return array{propre: bool, menace: ?string} */
    public function analyser(string $cheminAbsolu): array
    {
        if (! $this->estActif()) {
            return ['propre' => true, 'menace' => null];
        }

        return $this->scanner->analyser($cheminAbsolu);
    }

    public function verifier(string $cheminAbsolu): void
    {
        if (! $this->estActif()) {
            return;
        }

        $resultat = $this->scanner->analyser($cheminAbsolu);

        if (! $resultat['propre']) {
            Log::warning('ged.antivirus.rejet', [
                'chemin' => $cheminAbsolu,
                'menace' => $resultat['menace'],
            ]);

            throw new RuntimeException('Fichier rejeté par l\'analyse antivirus : '.($resultat['menace'] ?? 'menace inconnue'));
        }
    }

    public function estActif(): bool
    {
        return (bool) config('ged.antivirus.active', false)
            && ! $this->scanner instanceof NullAntivirusScanner;
    }

    /** État pour l'administration GED / supervision. */
    public function statut(): array
    {
        $cfg = config('ged.antivirus', []);
        $actif = (bool) ($cfg['active'] ?? false);
        $driver = (string) ($cfg['driver'] ?? 'null');
        $scannerOk = $this->scanner instanceof ClamAvScanner
            ? $this->scanner->disponible()
            : ! ($this->scanner instanceof NullAntivirusScanner);

        return [
            'actif' => $actif,
            'driver' => $driver,
            'quarantaine' => (bool) ($cfg['quarantine'] ?? true),
            'scanner_disponible' => $actif ? $scannerOk : null,
            'protection_effective' => $actif && $scannerOk && ! ($this->scanner instanceof NullAntivirusScanner),
            'clamscan_path' => $cfg['clamscan_path'] ?? 'clamscan',
        ];
    }
}
