<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase S4 — permissions suivi_physique.* (alias vers suivi.*). */
class SuiviPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $actions = [
            'view' => 'Consulter',
            'create' => 'Saisir',
            'update' => 'Modifier',
            'validate' => 'Valider',
            'export' => 'Exporter',
            'admin' => 'Administrer',
        ];

        foreach ($actions as $action => $libelle) {
            Permission::updateOrCreate(
                ['name' => "suivi_physique.{$action}", 'guard_name' => 'web'],
                ['module' => 'suivi_physique', 'action' => $action, 'libelle' => "Suivi physique — {$libelle}"]
            );
        }

        Role::findByName('administrateur', 'web')?->givePermissionTo('suivi_physique.admin');
        Role::findByName('commissaire', 'web')?->givePermissionTo(['suivi_physique.validate', 'suivi_physique.export']);
        Role::findByName('chef_service', 'web')?->givePermissionTo(['suivi_physique.create', 'suivi_physique.export']);
        Role::findByName('point_focal', 'web')?->givePermissionTo('suivi_physique.create');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
