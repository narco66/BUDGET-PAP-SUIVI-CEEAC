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

    public function test_export_inventaire_csv(): void
    {
        $admin = User::factory()->create(['actif' => true])->assignRole('administrateur');

        $this->actingAs($admin)->get(route('admin.interoperabilite.exports.inventaire', 'csv'))
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
