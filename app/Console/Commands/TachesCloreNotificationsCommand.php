<?php

namespace App\Console\Commands;

use App\Models\WorkflowTache;
use App\Services\Workflow\TacheWorkflowService;
use Illuminate\Console\Command;

/**
 * Rattrapage : les notifications « en attente de décision » de tâches déjà traitées ou annulées sont marquées
 * lues et traitées (elles ne doivent plus apparaître comme à traiter). Idempotent.
 */
class TachesCloreNotificationsCommand extends Command
{
    protected $signature = 'taches:clore-notifications';

    protected $description = 'Marque traitées les notifications des tâches déjà closes';

    public function handle(TacheWorkflowService $taches): int
    {
        $total = 0;
        WorkflowTache::query()->whereIn('statut', ['terminee', 'annulee'])->with('terminePar:id,name')
            ->chunkById(200, function ($lot) use ($taches, &$total) {
                foreach ($lot as $tache) {
                    $total += $taches->cloreNotifications($tache, $tache->terminePar?->name);
                }
            });
        $this->info("Notifications marquées traitées : {$total}.");

        return self::SUCCESS;
    }
}
