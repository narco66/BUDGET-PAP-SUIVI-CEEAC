<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P0 — Gouvernance unifiée, généralisation au module RBM/GAR (par exercice) : l'archivage des
 * exports Excel (matrice, cadre logique, indicateurs, catalogues par niveau) et PDF produit un
 * rapport officiel traçable via {@see GouvernanceExportService}, sans altérer les exports rapides.
 */
class GouvernanceExportRbmTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        Storage::fake('local');
    }

    /** Utilisateur doté des permissions RBM ciblées, sans ged.creer (chemin sans GED). */
    private function exportateur(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo(['rbm.exporter', 'rbm.generer_pdf']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_archive_excel_matrice(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('rbm.export.archiver', [$this->exercice, 'matrice']))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_rbm_matrice')->firstOrFail();
        $this->assertSame('excel', $rapport->format);
        $this->assertSame($this->exercice->id, $rapport->exercice_id);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.xlsx', $rapport->chemin);
    }

    public function test_archive_excel_niveau(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('rbm.export.archiver', [$this->exercice, 'pilier']))
            ->assertRedirect();

        $this->assertDatabaseHas('rapports', ['type' => 'export_rbm_niveau_pilier', 'format' => 'excel']);
    }

    public function test_archive_pdf_matrice(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('rbm.pdf.archiver', [$this->exercice, 'matrice']))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_rbm_matrice_pdf')->firstOrFail();
        $this->assertSame('pdf', $rapport->format);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.pdf', $rapport->chemin);
    }

    public function test_archive_pdf_catalogue_niveau(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('rbm.pdf.archiver', [$this->exercice, 'pilier']))
            ->assertRedirect();

        $this->assertDatabaseHas('rapports', ['type' => 'export_rbm_catalogue_pilier_pdf', 'format' => 'pdf']);
    }

    public function test_archive_refuse_sans_permission(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true]))
            ->get(route('rbm.export.archiver', [$this->exercice, 'matrice']))
            ->assertForbidden();

        $this->assertSame(0, Rapport::count());
    }

    public function test_jeu_inconnu_retourne_404(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('rbm.export.archiver', [$this->exercice, 'inexistant']))
            ->assertNotFound();

        $this->assertSame(0, Rapport::count());
    }

    public function test_export_rapide_existant_reste_disponible(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('rbm.export.matrice', $this->exercice))
            ->assertOk();

        $this->assertSame(0, Rapport::count());
    }
}
