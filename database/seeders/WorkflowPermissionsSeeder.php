<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase W-P0 — permissions granulaires module Workflow institutionnel. */
class WorkflowPermissionsSeeder extends Seeder
{
    /** @var array<string, string> */
    private array $actions = [
        'view' => 'Consulter les circuits et l\'historique workflow',
        'create' => 'Créer / configurer un circuit',
        'update' => 'Modifier un circuit',
        'validate' => 'Valider une étape workflow',
        'reject' => 'Rejeter ou renvoyer dans un circuit',
        'admin' => 'Administrer le module workflow',
        'audit' => 'Consulter le journal audit workflow',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->actions as $action => $libelle) {
            Permission::updateOrCreate(
                ['name' => "workflow.$action", 'guard_name' => 'web'],
                ['module' => 'workflow', 'action' => $action, 'libelle' => $libelle]
            );
        }

        $tous = collect($this->actions)->keys()->map(fn ($a) => "workflow.$a")->all();

        Role::findByName('administrateur', 'web')?->givePermissionTo($tous);

        Role::findByName('president', 'web')?->givePermissionTo([
            'workflow.view', 'workflow.validate', 'workflow.audit',
        ]);
        Role::findByName('secretaire_general', 'web')?->givePermissionTo([
            'workflow.view', 'workflow.validate', 'workflow.reject', 'workflow.audit',
        ]);
        Role::findByName('controleur_financier', 'web')?->givePermissionTo([
            'workflow.view', 'workflow.validate', 'workflow.reject', 'workflow.audit',
        ]);
        Role::findByName('directeur', 'web')?->givePermissionTo([
            'workflow.view', 'workflow.validate', 'workflow.reject',
        ]);
        Role::findByName('chef_service', 'web')?->givePermissionTo([
            'workflow.view', 'workflow.validate', 'workflow.reject',
        ]);
        Role::findByName('gestionnaire_budgetaire', 'web')?->givePermissionTo([
            'workflow.view', 'workflow.validate', 'workflow.reject',
        ]);
        Role::findByName('comptable', 'web')?->givePermissionTo([
            'workflow.view', 'workflow.validate',
        ]);
        Role::findByName('auditeur', 'web')?->givePermissionTo([
            'workflow.view', 'workflow.audit',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
