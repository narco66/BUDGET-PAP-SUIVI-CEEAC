<?php

namespace Tests\Feature;

use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0 — Gouvernance unifiée, généralisation au module Institution (hors exercice) : l'archivage de
 * l'annuaire institutionnel (Excel / PDF) produit un rapport officiel traçable via
 * {@see GouvernanceExportService}, sans altérer les exports rapides existants.
 */
class GouvernanceExportInstitutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_archive_annuaire_excel(): void
    {
        // « directeur » a structure.voir + structure.exporter, sans ged.creer.
        $this->actingAs($this->user('directeur'))
            ->get(route('structure.exporter.annuaire.archiver', 'excel'))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_institution_annuaire')->firstOrFail();
        $this->assertSame('excel', $rapport->format);
        $this->assertNull($rapport->exercice_id); // institution = hors exercice
        $this->assertSame('Annuaire institutionnel', $rapport->titre);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.xlsx', $rapport->chemin);
    }

    public function test_archive_annuaire_pdf(): void
    {
        $this->actingAs($this->user('directeur'))
            ->get(route('structure.exporter.annuaire.archiver', 'pdf'))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_institution_annuaire_pdf')->firstOrFail();
        $this->assertSame('pdf', $rapport->format);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.pdf', $rapport->chemin);
    }

    public function test_archive_refuse_sans_permission_export(): void
    {
        // « lecteur » a structure.voir mais pas structure.exporter.
        $this->actingAs($this->user('lecteur'))
            ->get(route('structure.exporter.annuaire.archiver', 'excel'))
            ->assertForbidden();

        $this->assertSame(0, Rapport::count());
    }

    public function test_format_inconnu_retourne_404(): void
    {
        $this->actingAs($this->user('directeur'))
            ->get(route('structure.exporter.annuaire.archiver', 'word'))
            ->assertNotFound();

        $this->assertSame(0, Rapport::count());
    }

    public function test_export_rapide_existant_reste_disponible(): void
    {
        $this->actingAs($this->user('directeur'))
            ->get(route('structure.exporter.annuaire.excel'))
            ->assertOk();

        $this->assertSame(0, Rapport::count());
    }
}
