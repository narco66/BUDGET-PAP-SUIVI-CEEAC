<?php

namespace App\Console\Commands;

use App\Services\Workflow\WorkflowRelanceService;
use Illuminate\Console\Command;

/** Relances automatiques sur étapes workflow en retard de SLA (P1). */
class WorkflowRelancesCommand extends Command
{
    protected $signature = 'workflow:relances';

    protected $description = 'Envoie les relances pour les validations workflow dont l\'échéance est dépassée';

    public function handle(WorkflowRelanceService $service): int
    {
        $count = $service->traiter();
        $this->info("Relances workflow envoyées : {$count}.");

        return self::SUCCESS;
    }
}
