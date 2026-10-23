<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\AliasDemonstrationService;
use App\Services\Institution\CadreOrganiqueCeeacService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase A3 — rapprochement alias démo → cadre officiel CEEAC. */
class ReferentielAdministratifPhaseA3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(StructureSeeder::class);
    }

    public function test_alias_sont_lies_aux_structures_officielles(): void
    {
        $deptPlan = Structure::where('code', 'DEPT-PLAN')->firstOrFail();
        $dirBudget = Structure::where('code', 'DIR-BUDGET')->firstOrFail();
        $svcExec = Structure::where('code', 'SVC-EXEC')->firstOrFail();

        $this->assertSame('DSG', $deptPlan->structureOfficielle?->code);
        $this->assertSame('DIR-PPB', $dirBudget->structureOfficielle?->code);
        $this->assertSame('SVC-BUDGET', $svcExec->structureOfficielle?->code);
        $this->assertTrue($dirBudget->estAliasDemonstration());
    }

    public function test_hierarchie_alias_est_coherente(): void
    {
        $this->assertSame('SG', Structure::where('code', 'DEPT-PLAN')->firstOrFail()->parent?->code);
        $this->assertSame('DIR-PPB', Structure::where('code', 'DIR-BUDGET')->firstOrFail()->parent?->code);
        $this->assertSame('DIR-BUDGET', Structure::where('code', 'SVC-EXEC')->firstOrFail()->parent?->code);
    }

    public function test_structure_effective_resout_l_alias(): void
    {
        $alias = Structure::where('code', 'DIR-BUDGET')->firstOrFail();

        $this->assertSame('DIR-PPB', $alias->structureEffective()->code);
    }

    public function test_reorientation_donnees_metier_vers_officiel(): void
    {
        $alias = Structure::where('code', 'SVC-EXEC')->firstOrFail();
        $officiel = Structure::where('code', 'SVC-BUDGET')->firstOrFail();

        $user = User::factory()->create([
            'structure_id' => $alias->id,
            'actif' => true,
        ]);

        $stats = app(AliasDemonstrationService::class)->reorienterDonneesMetier();

        $this->assertGreaterThan(0, $stats['users'] ?? 0);
        $this->assertSame($officiel->id, $user->fresh()->structure_id);
    }

    public function test_audit_alias_demonstration_sans_anomalie(): void
    {
        $this->assertSame([], app(AliasDemonstrationService::class)->listerAnomalies());
        $this->artisan('institution:audit-cadre')->assertSuccessful();
    }

    public function test_resynchronisation_conserve_les_liens_alias(): void
    {
        app(CadreOrganiqueCeeacService::class)->synchroniser();

        $this->assertSame(
            'DIR-PPB',
            Structure::where('code', 'DIR-BUDGET')->firstOrFail()->structureOfficielle?->code
        );
    }
}
