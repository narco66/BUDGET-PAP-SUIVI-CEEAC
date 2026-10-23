<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Permissions granulaires par niveau RBM (phase 2) — non destructif. */
class RbmGranularPermissionsSeeder extends Seeder
{
    /** @var array<string,string> */
    private array $niveaux = [
        'pilier' => 'Piliers RBM',
        'axe' => 'Axes stratégiques',
        'produit' => 'Produits',
        'sous_produit' => 'Sous-produits',
        'activite' => 'Activités',
        'tache' => 'Tâches',
        'cadre_logique' => 'Cadres logiques',
    ];

    /** @var array<string,string> */
    private array $actions = [
        'voir' => 'Voir',
        'gerer' => 'Gérer (CRUD)',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->niveaux as $module => $libModule) {
            foreach ($this->actions as $action => $libAction) {
                Permission::updateOrCreate(
                    ['name' => "{$module}.{$action}", 'guard_name' => 'web'],
                    ['module' => $module, 'action' => $action, 'libelle' => "{$libModule} — {$libAction}"]
                );
            }
        }

        $admin = Role::findByName('administrateur', 'web');
        $perms = Permission::whereIn('module', array_keys($this->niveaux))->pluck('name')->all();
        $admin?->givePermissionTo($perms);

        // Directeurs : gestion activités/tâches
        $directeur = Role::findByName('directeur', 'web');
        $directeur?->givePermissionTo(['activite.gerer', 'tache.gerer', 'axe.voir', 'produit.voir']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
