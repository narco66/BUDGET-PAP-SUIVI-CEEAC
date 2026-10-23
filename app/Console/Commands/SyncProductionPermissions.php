<?php

namespace App\Console\Commands;

use Database\Seeders\ProductionPermissionsSeeder;
use Illuminate\Console\Command;

/** Phase P3 — synchronisation des permissions en production sans données de démo. */
class SyncProductionPermissions extends Command
{
    protected $signature = 'permissions:sync-production {--force : Exécuter sans confirmation en production}';

    protected $description = 'Synchronise rôles et permissions (idempotent, sans données métier)';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            if (! $this->confirm('Synchroniser les permissions en production ?')) {
                $this->comment('Opération annulée.');

                return self::SUCCESS;
            }
        }

        $this->call('db:seed', [
            '--class' => ProductionPermissionsSeeder::class,
            '--force' => true,
        ]);

        $this->info('Permissions synchronisées (ProductionPermissionsSeeder).');

        return self::SUCCESS;
    }
}
