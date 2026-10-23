<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase P0 — permissions granulaires module Reporting institutionnel. */
class ReportingPermissionsSeeder extends Seeder
{
    /** @var array<string, string> */
    private array $actions = [
        'view' => 'Consulter le hub reporting et les aperçus',
        'create' => 'Configurer un rapport (filtres, modèles)',
        'generate' => 'Générer et signer un rapport',
        'validate' => 'Valider un rapport (MOA)',
        'publish' => 'Publier un rapport institutionnel',
        'export' => 'Exporter (PDF, Excel, Word, CSV)',
        'admin' => 'Administrer le module reporting',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->actions as $action => $libelle) {
            Permission::updateOrCreate(
                ['name' => "reporting.$action", 'guard_name' => 'web'],
                ['module' => 'reporting', 'action' => $action, 'libelle' => $libelle]
            );
        }

        $tous = [
            'reporting.view', 'reporting.create', 'reporting.generate',
            'reporting.validate', 'reporting.publish', 'reporting.export', 'reporting.admin',
        ];

        Role::findByName('administrateur', 'web')?->givePermissionTo($tous);
        Role::findByName('president', 'web')?->givePermissionTo([
            'reporting.view', 'reporting.generate', 'reporting.export', 'reporting.publish',
        ]);
        Role::findByName('secretaire_general', 'web')?->givePermissionTo([
            'reporting.view', 'reporting.generate', 'reporting.export', 'reporting.validate', 'reporting.publish',
        ]);
        Role::findByName('commissaire', 'web')?->givePermissionTo([
            'reporting.view', 'reporting.generate', 'reporting.export',
        ]);
        Role::findByName('controleur_financier', 'web')?->givePermissionTo([
            'reporting.view', 'reporting.validate',
        ]);
        Role::findByName('directeur', 'web')?->givePermissionTo(['reporting.view']);
        Role::findByName('auditeur', 'web')?->givePermissionTo(['reporting.view', 'reporting.export']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
