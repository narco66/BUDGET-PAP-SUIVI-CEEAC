<?php

namespace App\Console\Commands;

use App\Models\BackupSchedule;
use App\Services\AdministrationTechnique\BackupService;
use Illuminate\Console\Command;

/** Exécute une sauvegarde automatique puis purge selon la rétention (Lot T3). À planifier. */
class BackupExecuterCommand extends Command
{
    protected $signature = 'backup:executer {--type=base : base|fichiers|complet}';

    protected $description = 'Réalise une sauvegarde et applique la rétention';

    public function handle(BackupService $service): int
    {
        $type = $this->option('type');
        $backup = $service->executer($type, 'auto');
        $this->info("Sauvegarde {$backup->reference} : {$backup->statut}.");

        BackupSchedule::where('actif', true)->where('type', $type)->update(['derniere_execution_le' => now()]);

        $retention = (int) (BackupSchedule::where('actif', true)->min('retention_jours') ?? 30);
        $purges = $service->purger($retention);
        if ($purges) {
            $this->info("Purge : {$purges} sauvegarde(s) au-delà de {$retention} jours.");
        }

        return $backup->statut === 'succes' ? self::SUCCESS : self::FAILURE;
    }
}
