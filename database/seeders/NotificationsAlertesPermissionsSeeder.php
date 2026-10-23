<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase N-P0 — permissions granulaires module Notifications & Alertes. */
class NotificationsAlertesPermissionsSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private array $modules = [
        'notifications' => [
            'view' => 'Consulter le centre de notifications',
            'manage' => 'Gérer ses préférences de notifications',
            'send' => 'Émettre des notifications métier',
            'admin' => 'Administrer le module notifications',
        ],
        'alertes' => [
            'view' => 'Consulter les alertes institutionnelles',
            'manage' => 'Paramétrer les règles d\'alertes',
            'resolve' => 'Résoudre / clôturer une alerte',
            'admin' => 'Administrer le module alertes',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->modules as $module => $actions) {
            foreach ($actions as $action => $libelle) {
                Permission::updateOrCreate(
                    ['name' => "{$module}.{$action}", 'guard_name' => 'web'],
                    ['module' => $module, 'action' => $action, 'libelle' => $libelle]
                );
            }
        }

        $notifToutes = collect($this->modules['notifications'])->keys()->map(fn ($a) => "notifications.{$a}")->all();
        $alertesToutes = collect($this->modules['alertes'])->keys()->map(fn ($a) => "alertes.{$a}")->all();

        Role::findByName('administrateur', 'web')?->givePermissionTo(array_merge($notifToutes, $alertesToutes));

        foreach ([
            'president', 'secretaire_general', 'commissaire', 'directeur',
            'chef_service', 'gestionnaire_budgetaire', 'controleur_financier',
            'comptable', 'auditeur', 'point_focal',
        ] as $roleName) {
            Role::findByName($roleName, 'web')?->givePermissionTo([
                'notifications.view', 'notifications.manage', 'alertes.view',
            ]);
        }

        foreach (['controleur_financier', 'commissaire', 'secretaire_general', 'directeur', 'chef_service'] as $roleName) {
            Role::findByName($roleName, 'web')?->givePermissionTo([
                'notifications.send', 'alertes.resolve',
            ]);
        }

        Role::findByName('auditeur', 'web')?->givePermissionTo(['alertes.manage']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
