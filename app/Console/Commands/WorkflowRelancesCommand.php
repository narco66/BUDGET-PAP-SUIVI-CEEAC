<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationRappelService;
use App\Services\Workflow\WorkflowRelanceService;
use Illuminate\Console\Command;

/** Rappels (24 h / 48 h), alertes de retard et escalades des validations en attente (référentiel Notifications § 17). */
class WorkflowRelancesCommand extends Command
{
    protected $signature = 'workflow:relances';

    protected $description = 'Envoie les rappels, alertes de retard et escalades des validations workflow en attente';

    public function handle(WorkflowRelanceService $service, NotificationRappelService $rappels): int
    {
        $bilan = $rappels->traiter();
        $count = $service->traiter();
        $this->info("Rappels : {$bilan['rappels']} ; alertes de retard : {$count} ; escalades : {$bilan['escalades']}.");

        return self::SUCCESS;
    }
}
