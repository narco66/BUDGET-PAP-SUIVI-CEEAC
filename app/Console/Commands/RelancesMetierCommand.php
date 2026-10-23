<?php

namespace App\Console\Commands;

use App\Services\Alertes\RelanceMetierService;
use Illuminate\Console\Command;

/** Relances métier paramétrables PAP / budget / dépenses (N-P1). */
class RelancesMetierCommand extends Command
{
    protected $signature = 'relances:metier {--exercice= : ID exercice (optionnel)}';

    protected $description = 'Exécute les règles de relance métier configurées';

    public function handle(RelanceMetierService $service): int
    {
        $exerciceId = $this->option('exercice') ? (int) $this->option('exercice') : null;
        $count = $service->executer($exerciceId);
        $this->info("{$count} relance(s) métier émise(s).");

        return self::SUCCESS;
    }
}
