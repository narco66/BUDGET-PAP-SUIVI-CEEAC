<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Phase B1–B4 — permissions budget étendues (arbitrage, consolidation, simulation, rectificatif). */
class BudgetPermissionsSeeder extends Seeder
{
    /** @var array<string,string> */
    private array $actions = [
        'arbitrer' => 'Arbitrer',
        'consolider' => 'Consolider',
        'simuler' => 'Simuler',
        'rectifier' => 'Rectifier',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->actions as $action => $libAction) {
            Permission::updateOrCreate(
                ['name' => "budget.$action", 'guard_name' => 'web'],
                ['module' => 'budget', 'action' => $action, 'libelle' => "Enveloppe budgétaire — $libAction"]
            );
        }

        Role::findByName('controleur_financier', 'web')?->givePermissionTo([
            'budget.arbitrer', 'budget.consolider', 'budget.simuler', 'budget.rectifier',
        ]);
        Role::findByName('directeur', 'web')?->givePermissionTo(['budget.consolider', 'budget.simuler', 'budget.rectifier']);
        Role::findByName('gestionnaire_budgetaire', 'web')?->givePermissionTo(['budget.consolider', 'budget.simuler', 'budget.rectifier']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
