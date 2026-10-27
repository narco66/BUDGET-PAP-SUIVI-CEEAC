<?php

namespace Database\Seeders;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SuiviFinancierPermissionsSeeder::class,
            RecettesPermissionsSeeder::class,
            BudgetPermissionsSeeder::class,
            StrategiePermissionsSeeder::class,
            AuditControleConformiteSeeder::class,
            AdministrationTechniqueSeeder::class,
            ProfilSeeder::class,
            PosteSeeder::class,
            StructureSeeder::class,
            ExerciceSeeder::class,
            SourceFinancementSeeder::class,
            EtatMembreSeeder::class,
            RecetteSeeder::class,
            // La nomenclature doit précéder la reprise des recettes : la ventilation (rubrique_id) en dépend.
            NomenclatureBudgetaireSeeder::class,
            GestionRecettesSeeder::class,
            Pap2026Seeder::class,
            Budget2026Seeder::class,
            WorkflowSeeder::class,
            WorkflowPermissionsSeeder::class,
            ImportPermissionsSeeder::class,
            ImportWorkflowSeeder::class,
            NotificationsAlertesSeeder::class,
        ]);

        // Compte administrateur système initial
        $admin = User::updateOrCreate(
            ['email' => 'dsi.ceeac.eccas@gmail.com'],
            [
                'name' => 'Administrateur Système',
                'matricule' => 'ADM-001',
                'password' => Hash::make('password'), // À changer à la première connexion
                'structure_id' => Structure::where('code', 'SG')->value('id'),
                'actif' => true,
            ]
        );
        $admin->syncRoles(['administrateur']);

        $this->call([
            PapP0Seeder::class,
            BudgetB0Seeder::class,
            BudgetB1Seeder::class,
            BudgetB2Seeder::class,
            BudgetB3Seeder::class,
            BudgetB4Seeder::class,
        ]);
    }
}
