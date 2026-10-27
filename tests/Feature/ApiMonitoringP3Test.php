<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\ApiMetric;
use App\Models\User;
use App\Services\Interop\ApiMonitoringService;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase I-P3 — dashboard monitoring et agrégats API. */
class ApiMonitoringP3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InteroperabiliteApiPermissionsSeeder::class]);
    }

    public function test_kpis_monitoring(): void
    {
        ApiLog::create([
            'methode' => 'GET', 'chemin' => 'api/v1/exercices', 'statut' => 200,
            'statut_metier' => 'succes', 'duree_ms' => 100, 'created_at' => now(),
        ]);
        ApiLog::create([
            'methode' => 'GET', 'chemin' => 'api/v1/exercices', 'statut' => 500,
            'statut_metier' => 'erreur', 'duree_ms' => 200, 'created_at' => now(),
        ]);

        $kpis = app(ApiMonitoringService::class)->kpis(now()->subHour());
        $this->assertSame(2, $kpis['appels_total']);
        $this->assertSame(1, $kpis['appels_succes']);
        $this->assertSame(1, $kpis['appels_erreur']);
    }

    public function test_agregation_metriques_commande(): void
    {
        ApiLog::create([
            'methode' => 'GET', 'chemin' => 'api/v1/paps', 'statut' => 200,
            'statut_metier' => 'succes', 'duree_ms' => 50,
            'created_at' => now()->subDay(),
        ]);

        Artisan::call('api:agreger-metriques', ['--jour' => now()->subDay()->toDateString()]);

        $this->assertDatabaseHas('api_metrics', [
            'chemin' => 'api/v1/paps',
            'appels_total' => 1,
        ]);
    }

    public function test_dashboard_monitoring_admin(): void
    {
        $admin = User::factory()->create(['actif' => true])->assignRole('controleur_financier');

        $this->actingAs($admin)->get(route('admin.interoperabilite.monitoring'))->assertOk();
    }

    public function test_api_monitoring_interdit_et_kpis(): void
    {
        ApiLog::create([
            'methode' => 'GET', 'chemin' => 'api/v1/exercices', 'statut' => 200,
            'statut_metier' => 'succes', 'duree_ms' => 100, 'created_at' => now(),
        ]);
        ApiLog::create([
            'methode' => 'GET', 'chemin' => 'api/v1/exercices', 'statut' => 500,
            'statut_metier' => 'erreur', 'duree_ms' => 200, 'created_at' => now(),
        ]);

        $chef = User::factory()->create(['actif' => true])->assignRole('chef_service');
        $admin = User::factory()->create(['actif' => true])->assignRole('administrateur');

        $this->actingAs($chef)
            ->getJson(route('api.interoperabilite.monitoring'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson(route('api.interoperabilite.monitoring', ['jours' => 1]))
            ->assertOk()
            ->assertJsonPath('kpis.appels_total', 3)
            ->assertJsonPath('kpis.appels_succes', 1)
            ->assertJsonPath('kpis.appels_erreur', 2)
            ->assertJsonPath('kpis.appels_rejet', 0)
            ->assertJsonPath('top_chemins.0.chemin', 'api/v1/exercices')
            ->assertJsonPath('top_chemins.0.total', 2)
            ->assertJsonMissingPath('kpis.payload')
            ->assertJsonMissingPath('user_agent');
    }

    public function test_export_inventaire_csv(): void
    {
        $admin = User::factory()->create(['actif' => true])->assignRole('administrateur');

        $this->actingAs($admin)->get(route('admin.interoperabilite.exports.inventaire', 'csv'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_api_exports_interdit_et_fichiers(): void
    {
        $chef = User::factory()->create(['actif' => true])->assignRole('chef_service');
        $admin = User::factory()->create(['actif' => true])->assignRole('administrateur');

        $this->actingAs($chef)
            ->get(route('api.interoperabilite.exports.inventaire', 'csv'))
            ->assertForbidden();
        $this->actingAs($chef)
            ->get(route('api.interoperabilite.exports.securite', 'csv'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('api.interoperabilite.exports.inventaire', 'csv'))
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->actingAs($admin)
            ->get(route('api.interoperabilite.exports.securite', 'csv'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_endpoints_metier_pap_budget(): void
    {
        $user = User::factory()->create(['actif' => true])->assignRole('administrateur');
        Sanctum::actingAs($user, ['lecture']);

        $this->getJson(route('api.paps.index'))->assertOk();
        $this->getJson(route('api.budgets.index'))->assertOk();
    }
}
