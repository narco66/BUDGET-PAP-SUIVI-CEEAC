<?php

namespace App\Services\Ged\Antivirus;

use App\Contracts\Ged\GedAntivirusScannerInterface;
use Symfony\Component\Process\Process;

/** Scanner ClamAV via clamscan (si installé sur le serveur). */
class ClamAvScanner implements GedAntivirusScannerInterface
{
    public function __construct(private string $clamscanPath = 'clamscan') {}

    public function analyser(string $cheminAbsolu): array
    {
        if (! file_exists($cheminAbsolu)) {
            return ['propre' => false, 'menace' => 'fichier_introuvable'];
        }

        $process = new Process([$this->clamscanPath, '--no-summary', $cheminAbsolu]);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            return ['propre' => true, 'menace' => null];
        }

        $output = trim($process->getOutput().$process->getErrorOutput());

        return ['propre' => false, 'menace' => $output ?: 'menace_detectee'];
    }

    public function disponible(): bool
    {
        $process = new Process([$this->clamscanPath, '--version']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }
}
