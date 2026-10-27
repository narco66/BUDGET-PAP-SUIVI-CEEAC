<?php

namespace Tests\Feature;

use App\Models\ApiDocumentation;
use App\Models\ApiLog;
use App\Models\User;
use App\Services\Interop\ApiMonitoringService;
use App\Services\Interop\OpenApiService;
use Database\Seeders\ApiDocumentationSeeder;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase I-P3 — OpenAPI, monitoring, documentation. */
class OpenApiP3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InteroperabiliteApiPermissionsSeeder::class, ApiDocumentationSeeder::class]);
    }

    public function test_migration_p3_tables(): void
    {
        foreach (['api_metrics', 'api_documentations', 'api_audit_logs'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }

    public function test_spec_openapi_valide(): void
    {
        $service = app(OpenApiService::class);
        $this->assertTrue($service->estValide());
        $this->assertGreaterThanOrEqual(25, count($service->inventaireEndpoints()));
    }

    public function test_docs_swagger_accessible(): void
    {
        $admin = User::factory()->create(['actif' => true])->assignRole('directeur');

        $this->actingAs($admin)->get(route('api.docs'))->assertOk()->assertSee('swagger-ui');
        $this->actingAs($admin)->get(route('api.openapi.v1'))->assertOk()->assertJsonPath('info.version', '1.0.0');
    }

    public function test_api_docs_interdit_et_spec(): void
    {
        $chef = User::factory()->create(['actif' => true])->assignRole('chef_service');
        $admin = User::factory()->create(['actif' => true])->assignRole('administrateur');

        $this->actingAs($chef)
            ->getJson(route('api.interoperabilite.docs'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson(route('api.interoperabilite.docs'))
            ->assertOk()
            ->assertJsonPath('info.version', '1.0.0')
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonMissingPath('client_secret')
            ->assertJsonMissingPath('secret');
    }

    public function test_seeder_documentation(): void
    {
        $this->assertNotNull(ApiDocumentation::where('version', '1.0.0')->first());
    }
}
