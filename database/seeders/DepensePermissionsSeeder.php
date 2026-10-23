<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase D0 — permission admin chaîne de la dépense (dérogations tracées). */
class DepensePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::updateOrCreate(
            ['name' => 'depenses.admin', 'guard_name' => 'web'],
            ['module' => 'depenses', 'action' => 'admin', 'libelle' => 'Chaîne de la dépense — Administration / dérogation']
        );

        Role::findByName('administrateur', 'web')?->givePermissionTo('depenses.admin');
        Role::findByName('controleur_financier', 'web')?->givePermissionTo('depenses.admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
