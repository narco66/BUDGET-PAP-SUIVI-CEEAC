<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Permissions complémentaires Module 2 RBM/GAR (non destructif). */
class RbmPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $extras = [
            ['name' => 'rbm.archiver', 'module' => 'rbm', 'action' => 'archiver', 'libelle' => 'GAR / RBM — Archiver'],
            ['name' => 'rbm.restaurer', 'module' => 'rbm', 'action' => 'restaurer', 'libelle' => 'GAR / RBM — Restaurer'],
            ['name' => 'rbm.admin', 'module' => 'rbm', 'action' => 'admin', 'libelle' => 'GAR / RBM — Administration'],
        ];

        foreach ($extras as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['module' => $perm['module'], 'action' => $perm['action'], 'libelle' => $perm['libelle']]
            );
        }

        $admin = \Spatie\Permission\Models\Role::findByName('administrateur', 'web');
        $admin?->givePermissionTo(['rbm.archiver', 'rbm.restaurer', 'rbm.admin']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
