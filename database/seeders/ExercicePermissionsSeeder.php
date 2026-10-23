<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Permissions workflow exercices budgétaires (Phase A). */
class ExercicePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $extras = [
            ['name' => 'exercice.ouvrir', 'module' => 'exercice', 'action' => 'ouvrir', 'libelle' => 'Exercices budgétaires — Ouvrir'],
            ['name' => 'exercice.cloturer', 'module' => 'exercice', 'action' => 'cloturer', 'libelle' => 'Exercices budgétaires — Clôturer'],
            ['name' => 'exercice.activer', 'module' => 'exercice', 'action' => 'activer', 'libelle' => 'Exercices budgétaires — Définir actif'],
        ];

        foreach ($extras as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['module' => $perm['module'], 'action' => $perm['action'], 'libelle' => $perm['libelle']]
            );
        }

        $noms = collect($extras)->pluck('name')->all();

        Role::findByName('administrateur', 'web')?->givePermissionTo($noms);
        Role::findByName('gestionnaire_budgetaire', 'web')?->givePermissionTo($noms);
        Role::findByName('secretaire_general', 'web')?->givePermissionTo(['exercice.cloturer', 'exercice.activer']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
