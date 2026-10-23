<?php

namespace App\Console\Commands;

use App\Services\AdministrationTechnique\SanteService;
use Illuminate\Console\Command;

/** Capture un instantané de santé technique (supervision — Lot T1). À planifier au scheduler. */
class CapturerSanteCommand extends Command
{
    protected $signature = 'admin-technique:capturer-sante';

    protected $description = 'Enregistre un instantané de l\'état de santé technique de la plateforme';

    public function handle(SanteService $sante): int
    {
        $metric = $sante->capturer();
        $this->info("Santé capturée : {$metric->statut_global} ({$metric->capture_le}).");

        return self::SUCCESS;
    }
}
