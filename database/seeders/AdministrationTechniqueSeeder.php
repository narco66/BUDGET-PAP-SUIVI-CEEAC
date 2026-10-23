<?php

namespace Database\Seeders;

use App\Models\BackupSchedule;
use App\Models\IncidentCategorie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Module Administration Technique — permissions `admin_technique.*` (centre de supervision DSI).
 * Idempotent et non destructif. Commande : php artisan db:seed --class=AdministrationTechniqueSeeder
 */
class AdministrationTechniqueSeeder extends Seeder
{
    /** @var array<string,string> */
    private array $actions = [
        'view' => 'Consulter la supervision technique',
        'monitor' => 'Superviser la santé de la plateforme',
        'backup' => 'Gérer les sauvegardes',
        'restore' => 'Restaurer une sauvegarde',
        'security' => 'Consulter la sécurité technique',
        'logs' => 'Consulter les journaux techniques',
        'admin' => 'Administrer le module technique',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->actions as $action => $libelle) {
            Permission::updateOrCreate(
                ['name' => "admin_technique.$action", 'guard_name' => 'web'],
                ['module' => 'admin_technique', 'action' => $action, 'libelle' => "Administration technique — $libelle"]
            );
        }

        Role::findByName('administrateur', 'web')?->givePermissionTo(array_map(fn ($a) => "admin_technique.$a", array_keys($this->actions)));
        Role::findByName('auditeur', 'web')?->givePermissionTo(['admin_technique.view', 'admin_technique.monitor', 'admin_technique.security', 'admin_technique.logs']);

        // Catégories d'incidents (ITIL) — Lot T2.
        if (Schema::hasTable('incident_categories')) {
            $categories = [
                'SECU' => 'Sécurité', 'PERF' => 'Performance', 'DISPO' => 'Disponibilité',
                'BDD' => 'Base de données', 'INFRA' => 'Infrastructure',
            ];
            $i = 0;
            foreach ($categories as $code => $libelle) {
                IncidentCategorie::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'ordre' => ++$i]);
            }
        }

        // Planification de sauvegarde par défaut (Lot T3) — quotidienne BDD, rétention 30 jours.
        if (Schema::hasTable('backup_schedules')) {
            BackupSchedule::updateOrCreate(
                ['libelle' => 'Sauvegarde quotidienne de la base'],
                ['type' => 'base', 'frequence' => 'quotidienne', 'heure' => '02:00', 'retention_jours' => 30, 'actif' => true]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
