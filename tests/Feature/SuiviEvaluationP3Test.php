<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use App\Services\Suivi\SuiviEvaluationService;
use Database\Seeders\Demo\SuiviEvaluationDemoSeeder;
use Database\Seeders\ProductionPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviEvaluationPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Phase P3 — seeder démo, API REST suivi-évaluation. */
class SuiviEvaluationP3Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SuiviEvaluationPermissionsSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
    }

    private function commissaire(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('commissaire');
    }

    public function test_production_seeder_cree_permissions_suivi_evaluation(): void
    {
        $this->seed(ProductionPermissionsSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'suivi_evaluation.view', 'guard_name' => 'web']);
        $this->assertTrue(
            Permission::findByName('suivi_evaluation.validate', 'web')
                ->roles()
                ->where('name', 'commissaire')
                ->exists()
        );
    }

    public function test_demo_seeder(): void
    {
        $this->seed(SuiviEvaluationDemoSeeder::class);

        $this->assertDatabaseHas('missions_suivi', ['numero' => 'MS-2026-001']);
        $this->assertDatabaseHas('plans_amelioration', ['numero' => 'PA-2026-001']);
    }

    public function test_api_synthese_evaluation(): void
    {
        app(SuiviEvaluationService::class)->genererEvaluation($this->exercice->id, null, null);
        Sanctum::actingAs($this->commissaire(), ['suivi']);

        $this->getJson(route('api.suivi-evaluation.synthese', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['exercice_id', 'scores', 'niveau']]);
    }

    public function test_api_evaluations_periodiques(): void
    {
        app(SuiviEvaluationService::class)->genererEvaluation($this->exercice->id, null, null);
        Sanctum::actingAs($this->commissaire(), ['lecture']);

        $this->getJson(route('api.suivi-evaluation.evaluations', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_api_refuse_sans_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['actif' => true]), ['lecture']);

        $this->getJson(route('api.suivi-evaluation.synthese', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }
}
