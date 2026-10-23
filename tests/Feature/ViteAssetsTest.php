<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** P2-9 — assets front centralisés via Vite (Alpine, ApexCharts, styles CEEAC). */
class ViteAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['mfa.enabled' => false]);
        $this->manifest = json_decode(
            File::get(public_path('build/manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_aucun_cdn_alpine_ni_apexcharts_dans_les_vues(): void
    {
        $contenu = collect(File::allFiles(resource_path('views')))
            ->map(fn ($f) => File::get($f->getPathname()))
            ->implode("\n");

        $this->assertStringNotContainsString('alpinejs@3', $contenu);
        $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/apexcharts', $contenu);
    }

    public function test_manifest_vite_contient_les_entrees_centralisees(): void
    {
        $this->assertArrayHasKey('resources/js/app.js', $this->manifest);
        $this->assertArrayHasKey('resources/js/charts.js', $this->manifest);
        $this->assertArrayHasKey('resources/js/cockpit-live.js', $this->manifest);
        $this->assertArrayHasKey('resources/js/echo.js', $this->manifest);
        $this->assertArrayHasKey('resources/css/ceeac.css', $this->manifest);
    }

    public function test_layout_principal_charge_vite_alpine_et_styles(): void
    {
        $appJs = $this->manifest['resources/js/app.js']['file'];
        $ceeacCss = $this->manifest['resources/css/ceeac.css']['file'];

        $this->actingAs($this->user('administrateur'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($appJs, false)
            ->assertSee($ceeacCss, false);
    }

    public function test_tableau_de_bord_charge_apexcharts_via_vite(): void
    {
        $chartsJs = $this->manifest['resources/js/charts.js']['file'];

        $this->actingAs($this->user('secretaire_general'))
            ->get(route('tableaux.index'))
            ->assertOk()
            ->assertSee($chartsJs, false);
    }

    public function test_formulaires_alpine_via_layout_vite(): void
    {
        $appJs = $this->manifest['resources/js/app.js']['file'];

        $this->actingAs($this->user('administrateur'))
            ->get(route('engagements.create'))
            ->assertOk()
            ->assertSee('x-data', false)
            ->assertSee($appJs, false);
    }
}
