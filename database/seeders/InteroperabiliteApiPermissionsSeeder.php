<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase I-P0 — permissions granulaires module Interopérabilité & API. */
class InteroperabiliteApiPermissionsSeeder extends Seeder
{
    /** @var array<string, string> */
    private array $actions = [
        'view' => 'Consulter le catalogue et la documentation API',
        'create' => 'Émettre des jetons d\'accès API',
        'update' => 'Modifier la configuration des services API',
        'publish' => 'Publier ou activer des endpoints API',
        'monitor' => 'Consulter le journal et le monitoring des échanges',
        'admin' => 'Administrer le module interopérabilité',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->actions as $action => $libelle) {
            Permission::updateOrCreate(
                ['name' => "api.{$action}", 'guard_name' => 'web'],
                ['module' => 'api', 'action' => $action, 'libelle' => $libelle]
            );
        }

        $toutes = collect($this->actions)->keys()->map(fn ($a) => "api.{$a}")->all();

        Role::findByName('administrateur', 'web')?->givePermissionTo($toutes);

        foreach (['controleur_financier', 'gestionnaire_budgetaire', 'auditeur'] as $role) {
            Role::findByName($role, 'web')?->givePermissionTo(['api.view', 'api.create', 'api.monitor']);
        }

        foreach (['commissaire', 'secretaire_general', 'directeur'] as $role) {
            Role::findByName($role, 'web')?->givePermissionTo(['api.view', 'api.monitor']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
