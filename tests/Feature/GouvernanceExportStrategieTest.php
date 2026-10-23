<?php

namespace Tests\Feature;

use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StrategiePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0 — Gouvernance unifiée, généralisation au module Stratégie (hors exercice) : l'archivage des
 * catalogues Excel et des rapports PDF produit un rapport officiel traçable via
 * {@see GouvernanceExportService}, sans altérer les exports rapides existants.
 */
class GouvernanceExportStrategieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(StrategiePermissionsSeeder::class);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_archive_catalogue_excel(): void
    {
        // « directeur » possède strategie.export sans ged.creer : chemin ciblé sans GED.
        $this->actingAs($this->user('directeur'))
            ->get(route('strategie.export.archiver.excel', 'visions'))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_strategie_visions')->firstOrFail();
        $this->assertSame('excel', $rapport->format);
        $this->assertSame('Catalogue des visions stratégiques', $rapport->titre);
        $this->assertNull($rapport->exercice_id); // stratégie hors exercice
        $this->assertSame('genere', $rapport->statut_workflow);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.xlsx', $rapport->chemin);
    }

    public function test_archive_rapport_pdf(): void
    {
        $this->actingAs($this->user('directeur'))
            ->get(route('strategie.export.archiver.pdf', 'synthese'))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_strategie_synthese_pdf')->firstOrFail();
        $this->assertSame('pdf', $rapport->format);
        $this->assertSame('Synthèse de la planification stratégique', $rapport->titre);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.pdf', $rapport->chemin);
    }

    public function test_archive_refuse_sans_permission(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->get(route('strategie.export.archiver.excel', 'visions'))
            ->assertForbidden();

        $this->assertSame(0, Rapport::count());
    }

    public function test_catalogue_inconnu_retourne_404(): void
    {
        $this->actingAs($this->user('directeur'))
            ->get(route('strategie.export.archiver.excel', 'inexistant'))
            ->assertNotFound();

        $this->assertSame(0, Rapport::count());
    }

    public function test_export_rapide_existant_reste_disponible(): void
    {
        $this->actingAs($this->user('directeur'))
            ->get(route('strategie.export.excel', 'visions'))
            ->assertOk();

        $this->assertSame(0, Rapport::count());
    }
}
