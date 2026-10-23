<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use Database\Seeders\ProductionPermissionsSeeder;
use Database\Seeders\ReportingPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviFinancierPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Phase P3 — seeder production, API REST suivi financier / indicateurs. */
class SuiviP3Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SuiviFinancierPermissionsSeeder::class);
        $this->seed(ReportingPermissionsSeeder::class);
        $this->seed(InteroperabiliteApiPermissionsSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
    }

    private function controleur(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('controleur_financier');
    }

    public function test_production_seeder_cree_permissions_suivi_financier(): void
    {
        $this->seed(ProductionPermissionsSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'suivi_financier.view', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'suivi_financier.export', 'guard_name' => 'web']);
        $this->assertTrue(
            Permission::findByName('suivi_financier.view', 'web')
                ->roles()
                ->where('name', 'controleur_financier')
                ->exists()
        );
    }

    public function test_api_synthese_exige_authentification(): void
    {
        $this->getJson(route('api.suivi-financier.synthese', ['exercice' => $this->exercice->id]))
            ->assertUnauthorized();
    }

    public function test_api_synthese_refuse_sans_permission_metier(): void
    {
        Sanctum::actingAs(User::factory()->create(['actif' => true]), ['lecture']);

        $this->getJson(route('api.suivi-financier.synthese', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }

    public function test_api_synthese_accessible_controleur_scope_lecture(): void
    {
        Sanctum::actingAs($this->controleur(), ['lecture']);

        $this->getJson(route('api.suivi-financier.synthese', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['exercice_id', 'budget']]);
    }

    public function test_api_synthese_accessible_scope_suivi(): void
    {
        Sanctum::actingAs($this->controleur(), ['suivi']);

        $this->getJson(route('api.suivi-financier.synthese', ['exercice' => $this->exercice->annee]))
            ->assertOk();
    }

    public function test_api_ecarts_physique_financier(): void
    {
        Sanctum::actingAs($this->controleur(), ['suivi']);

        $this->getJson(route('api.suivi-financier.ecarts', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_api_feux_indicateurs(): void
    {
        Sanctum::actingAs($this->controleur(), ['lecture']);

        $this->getJson(route('api.suivi-indicateurs.feux', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('data.feux.vert', 0);
    }

    public function test_api_synthese_refuse_scope_tompro(): void
    {
        Sanctum::actingAs($this->controleur(), ['tompro']);

        $this->getJson(route('api.suivi-financier.synthese', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }

    public function test_emission_jeton_scope_suivi(): void
    {
        $user = $this->controleur();

        $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'password',
            'abilities' => ['suivi'],
        ])->assertCreated()
            ->assertJsonFragment(['abilities' => ['suivi']]);
    }
}
