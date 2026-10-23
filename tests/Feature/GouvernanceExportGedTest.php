<?php

namespace Tests\Feature;

use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P0 — Gouvernance unifiée, généralisation au module GED (cas spécial) : l'archivage des registres
 * GED produit un rapport officiel traçable via {@see GouvernanceExportService}, SANS réindexation
 * GED (un registre du module GED ne se réinjecte pas dans la GED — boucle conceptuelle).
 */
class GouvernanceExportGedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function exportateur(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo(['ged.exporter', 'ged.consulter_journal']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_archive_registre_documents_sans_reindexation_ged(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('ged.exports.registres.archiver', 'documents'))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_ged_registre_documents')->firstOrFail();
        $this->assertSame('excel', $rapport->format);
        Storage::disk('local')->assertExists($rapport->chemin);

        // Le cœur du cas spécial : pas de réindexation GED (aucune boucle).
        $this->assertFalse((bool) $rapport->ged_indexe);
        $this->assertDatabaseCount('ged_documents', 0);
    }

    public function test_archive_registre_journal(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('ged.exports.registres.archiver', 'journal'))
            ->assertRedirect();

        $this->assertDatabaseHas('rapports', ['type' => 'export_ged_journal', 'format' => 'excel']);
    }

    public function test_archive_refuse_sans_permission(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true]))
            ->get(route('ged.exports.registres.archiver', 'documents'))
            ->assertForbidden();

        $this->assertSame(0, Rapport::count());
    }

    public function test_registre_inconnu_retourne_404(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('ged.exports.registres.archiver', 'inexistant'))
            ->assertNotFound();

        $this->assertSame(0, Rapport::count());
    }

    public function test_export_rapide_existant_reste_disponible(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('ged.exports.documents.excel'))
            ->assertOk();

        $this->assertSame(0, Rapport::count());
    }
}
