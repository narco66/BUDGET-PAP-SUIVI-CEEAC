<?php

namespace Tests\Feature;

use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\Strategie\Vision;
use App\Models\User;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\PlanificationStrategiqueSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StrategiePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Phase P0 — schéma, seeders, permissions et cohérence relationnelle. */
class PlanificationStrategiquePhaseP0Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, ExerciceSeeder::class, StrategiePermissionsSeeder::class]);
    }

    public function test_tables_planification_strategique_existent(): void
    {
        foreach ([
            'visions', 'plans_strategiques', 'orientations_strategiques', 'programmes_strategiques',
            'projets', 'portefeuilles_projets', 'portefeuille_projet',
            'programmations_pluriannuelles', 'programmations_annuelles',
            'objectifs_strategiques', 'indicateurs_strategiques', 'risques_strategiques',
            'alignements_strategiques',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table manquante : {$table}");
        }
    }

    public function test_permissions_strategie_creees(): void
    {
        foreach ([
            'strategie.view', 'strategie.create', 'strategie.update', 'strategie.delete',
            'strategie.archive', 'strategie.validate', 'strategie.export', 'strategie.admin',
        ] as $perm) {
            $this->assertNotNull(Permission::findByName($perm, 'web'));
        }
    }

    public function test_seeder_planification_strategique_idempotent(): void
    {
        User::factory()->create(['actif' => true]);

        $this->seed(PlanificationStrategiqueSeeder::class);
        $this->seed(PlanificationStrategiqueSeeder::class);

        $this->assertSame(1, Vision::where('reference', 'VIS-2035')->count());
        $this->assertSame(1, PlanStrategique::where('reference', 'PSC-2025-2030')->count());
        $this->assertSame(4, OrientationStrategique::count());
        $this->assertSame(1, ProgrammeStrategique::where('code', 'PS-INT-ECO')->count());
        $this->assertSame(1, ProjetStrategique::where('code', 'PRJ-ZLECAF')->count());
    }

    public function test_coherence_hierarchique_vision_orientation_programme(): void
    {
        User::factory()->create(['actif' => true]);
        $this->seed(PlanificationStrategiqueSeeder::class);

        $programme = ProgrammeStrategique::with('orientationStrategique.planStrategique.vision')->first();

        $this->assertNotNull($programme);
        $this->assertNotNull($programme->orientationStrategique);
        $this->assertNotNull($programme->orientationStrategique->planStrategique);
        $this->assertNotNull($programme->orientationStrategique->planStrategique->vision);
        $this->assertSame('VIS-2035', $programme->orientationStrategique->planStrategique->vision->reference);
    }

    public function test_permissions_lecteur_et_admin(): void
    {
        $lecteur = User::factory()->create();
        $lecteur->assignRole('lecteur');

        $admin = User::factory()->create();
        $admin->assignRole('administrateur');

        $this->assertTrue($lecteur->can('strategie.view'));
        $this->assertFalse($lecteur->can('strategie.create'));
        $this->assertTrue($admin->can('strategie.admin'));
        $this->assertTrue($admin->can('strategie.create'));
    }

    public function test_projet_requiert_responsable(): void
    {
        User::factory()->create(['actif' => true]);
        $this->seed(PlanificationStrategiqueSeeder::class);

        $projet = ProjetStrategique::first();
        $this->assertNotNull($projet?->responsable_user_id);
        $this->assertNotNull($projet?->responsable);
    }
}
