<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Permissions complémentaires module GED (non destructif). */
class GedPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $extras = [
            ['name' => 'ged.archiver', 'module' => 'ged', 'action' => 'archiver', 'libelle' => 'GED — Archiver'],
            ['name' => 'ged.restaurer', 'module' => 'ged', 'action' => 'restaurer', 'libelle' => 'GED — Restaurer'],
            ['name' => 'ged.telecharger', 'module' => 'ged', 'action' => 'telecharger', 'libelle' => 'GED — Télécharger'],
            ['name' => 'ged.previsualiser', 'module' => 'ged', 'action' => 'previsualiser', 'libelle' => 'GED — Prévisualiser'],
            ['name' => 'ged.partager', 'module' => 'ged', 'action' => 'partager', 'libelle' => 'GED — Partager'],
            ['name' => 'ged.signer', 'module' => 'ged', 'action' => 'signer', 'libelle' => 'GED — Signer'],
            ['name' => 'ged.publier', 'module' => 'ged', 'action' => 'publier', 'libelle' => 'GED — Publier'],
            ['name' => 'ged.rechercher', 'module' => 'ged', 'action' => 'rechercher', 'libelle' => 'GED — Recherche avancée'],
            ['name' => 'ged.admin', 'module' => 'ged', 'action' => 'admin', 'libelle' => 'GED — Administration'],
        ];

        foreach ($extras as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['module' => $perm['module'], 'action' => $perm['action'], 'libelle' => $perm['libelle']]
            );
        }

        $admin = Role::findByName('administrateur', 'web');
        $admin?->givePermissionTo(collect($extras)->pluck('name')->all());

        $controleur = Role::findByName('controleur_financier', 'web');
        $controleur?->givePermissionTo(['ged.archiver', 'ged.valider', 'ged.rejeter', 'ged.signer']);

        $directeur = Role::findByName('directeur', 'web');
        $directeur?->givePermissionTo(['ged.creer', 'ged.modifier', 'ged.archiver', 'ged.partager']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
