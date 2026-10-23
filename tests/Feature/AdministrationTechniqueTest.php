<?php

namespace Tests\Feature;

use App\Models\SystemMetric;
use App\Models\User;
use App\Services\AdministrationTechnique\SanteService;
use Database\Seeders\AdministrationTechniqueSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot T1 — centre de supervision technique (santé + cockpit + endpoint /health). */
class AdministrationTechniqueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdministrationTechniqueSeeder::class);
        config(['mfa.enabled' => false]);

        $this->admin = User::create([
            'name' => 'DSI', 'email' => 'dsi@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_seeder_cree_les_permissions_admin_technique(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'admin_technique.monitor']);
        $this->assertTrue($this->admin->can('admin_technique.view'));
        $this->assertTrue($this->admin->can('admin_technique.admin'));
    }

    public function test_service_de_sante_produit_les_controles(): void
    {
        $sante = app(SanteService::class);
        $controles = $sante->controles();

        $cles = array_column($controles, 'cle');
        $this->assertContains('base', $cles);
        $this->assertContains('disque', $cles);
        $this->assertContains('redis', $cles);
        $this->assertContains('cache', $cles);
        $this->assertContains('jobs_echoues', $cles);
        $this->assertContains('planificateur', $cles);

        // La base répond → contrôle "base" au vert.
        $base = collect($controles)->firstWhere('cle', 'base');
        $this->assertSame('ok', $base['statut']);

        $this->assertContains($sante->statutGlobal($controles), ['ok', 'warning', 'critique']);
    }

    public function test_capture_persiste_un_instantane(): void
    {
        $this->artisan('admin-technique:capturer-sante')->assertSuccessful();
        $this->assertSame(1, SystemMetric::count());
        $this->assertNotNull(SystemMetric::first()->statut_global);
    }

    public function test_cockpit_accessible(): void
    {
        $this->actingAs($this->admin)->get(route('administration-technique.cockpit'))
            ->assertOk()
            ->assertSee('Centre de supervision technique', false)
            ->assertSee('Supervision des modules', false)
            ->assertSee('Base de données', false);
    }

    public function test_endpoint_health_public_renvoie_json(): void
    {
        // Sans authentification (sonde externe).
        $this->getJson(route('sante.health'))
            ->assertOk()
            ->assertJsonStructure(['statut', 'horodatage', 'controles']);
    }

    public function test_cockpit_refuse_sans_droit(): void
    {
        $lecteur = User::create([
            'name' => 'Lecteur', 'email' => 'lect.tech@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');

        $this->actingAs($lecteur)->get(route('administration-technique.cockpit'))->assertForbidden();
    }
}
