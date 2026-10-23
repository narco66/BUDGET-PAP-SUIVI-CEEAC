<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase P0 — Permissions module Planification Stratégique (nomenclature audit `strategie.*`). */
class StrategiePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            ['name' => 'strategie.view', 'action' => 'view', 'libelle' => 'Planification stratégique — Consulter'],
            ['name' => 'strategie.create', 'action' => 'create', 'libelle' => 'Planification stratégique — Créer'],
            ['name' => 'strategie.update', 'action' => 'update', 'libelle' => 'Planification stratégique — Modifier'],
            ['name' => 'strategie.delete', 'action' => 'delete', 'libelle' => 'Planification stratégique — Supprimer'],
            ['name' => 'strategie.archive', 'action' => 'archive', 'libelle' => 'Planification stratégique — Archiver'],
            ['name' => 'strategie.validate', 'action' => 'validate', 'libelle' => 'Planification stratégique — Valider'],
            ['name' => 'strategie.export', 'action' => 'export', 'libelle' => 'Planification stratégique — Exporter'],
            ['name' => 'strategie.admin', 'action' => 'admin', 'libelle' => 'Planification stratégique — Administration'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['module' => 'strategie', 'action' => $perm['action'], 'libelle' => $perm['libelle']]
            );
        }

        $names = array_column($permissions, 'name');

        Role::findByName('administrateur', 'web')?->givePermissionTo($names);

        foreach (['secretaire_general', 'directeur', 'commissaire'] as $roleName) {
            Role::findByName($roleName, 'web')?->givePermissionTo([
                'strategie.view', 'strategie.create', 'strategie.update',
                'strategie.export', 'strategie.validate', 'strategie.archive',
            ]);
        }

        Role::findByName('lecteur', 'web')?->givePermissionTo(['strategie.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
