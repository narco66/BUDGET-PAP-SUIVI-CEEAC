<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder NON DESTRUCTIF : complète la base sans rien écraser ni supprimer.
 * 1) Données de référence (seeders existants, idempotents via updateOrCreate).
 * 2) Données de démonstration (gardées par firstOrCreate / exists()).
 * N'utilise aucune opération destructive ; peut être relancé sans créer de doublon.
 */
class SafeDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Référentiels (idempotents)
        $this->call([
            RoleSeeder::class,
            DepensePermissionsSeeder::class,
            WorkflowPermissionsSeeder::class,
            MotifsRejetSeeder::class,
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
            ProfilSeeder::class,
            PosteSeeder::class,
            StructureSeeder::class,
            ExerciceSeeder::class,
            SourceFinancementSeeder::class,
            PartenaireFinancierSeeder::class,
            EtatMembreSeeder::class,
            RecetteSeeder::class,
            GestionRecettesSeeder::class,
            NomenclatureBudgetaireSeeder::class,
            Pap2026Seeder::class,
            PlanificationStrategiqueSeeder::class,
            RbmReferentielSeeder::class,
            Budget2026Seeder::class,
            WorkflowSeeder::class,
            GedSeeder::class,
        ]);

        // 2) Données de démonstration (non destructives, ordonnées par dépendance)
        $this->call([
            DemoDataSeeder::class,
        ]);
    }
}
