<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase P2 — permissions module Suivi Financier. */
class SuiviFinancierPermissionsSeeder extends Seeder
{
    /** @var array<string, string> */
    private array $actions = [
        'view' => 'Consulter le suivi financier',
        'export' => 'Exporter (restes, situations, rapports)',
        'validate' => 'Capturer / valider une situation budgétaire',
        'admin' => 'Administrer le module suivi financier',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->actions as $action => $libelle) {
            Permission::updateOrCreate(
                ['name' => "suivi_financier.$action", 'guard_name' => 'web'],
                ['module' => 'suivi_financier', 'action' => $action, 'libelle' => $libelle]
            );
        }

        Role::findByName('controleur_financier', 'web')?->givePermissionTo([
            'suivi_financier.view', 'suivi_financier.export', 'suivi_financier.validate',
        ]);
        Role::findByName('secretaire_general', 'web')?->givePermissionTo([
            'suivi_financier.view', 'suivi_financier.export',
        ]);
        Role::findByName('directeur', 'web')?->givePermissionTo(['suivi_financier.view']);
        Role::findByName('administrateur', 'web')?->givePermissionTo([
            'suivi_financier.view', 'suivi_financier.export', 'suivi_financier.validate', 'suivi_financier.admin',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
