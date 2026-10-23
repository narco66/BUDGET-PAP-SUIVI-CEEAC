<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder idempotent pour la production : synchronise uniquement rôles et permissions
 * sans toucher aux données métier. Relançable à chaque déploiement (phase P3).
 *
 * Usage :
 *   php artisan db:seed --class=ProductionPermissionsSeeder
 *   php artisan permissions:sync-production
 */
class ProductionPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            NotificationsAlertesPermissionsSeeder::class,
            InteroperabiliteApiPermissionsSeeder::class,
            WorkflowPermissionsSeeder::class,
            MotifsRejetSeeder::class,
            DepensePermissionsSeeder::class,
            SuiviFinancierPermissionsSeeder::class,
            SuiviEvaluationPermissionsSeeder::class,
            ReportingPermissionsSeeder::class,
            ReportingCatalogueSeeder::class,
            ExercicePermissionsSeeder::class,
            RbmPermissionsSeeder::class,
            RbmGranularPermissionsSeeder::class,
            StrategiePermissionsSeeder::class,
            GedPermissionsSeeder::class,
            RecettesPermissionsSeeder::class,
            BudgetPermissionsSeeder::class,
            SuiviPermissionsSeeder::class,
        ]);
    }
}
