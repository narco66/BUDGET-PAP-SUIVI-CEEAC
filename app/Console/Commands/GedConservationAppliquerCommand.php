<?php

namespace App\Console\Commands;

use App\Services\Ged\GedConservationService;
use Illuminate\Console\Command;

/** Applique les règles de conservation (archivage auto, alertes). */
class GedConservationAppliquerCommand extends Command
{
    protected $signature = 'ged:conservation-appliquer {--dry-run : Simulation sans modification}';

    protected $description = 'Applique les règles de conservation documentaire GED';

    public function handle(GedConservationService $conservation): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resultat = $conservation->appliquerRegles($dryRun);

        $this->info("Documents analysés : {$resultat['analyses']}");
        $this->info("Archivages auto : {$resultat['archives']} (dry-run: ".($dryRun ? 'oui' : 'non').')');

        return self::SUCCESS;
    }
}
