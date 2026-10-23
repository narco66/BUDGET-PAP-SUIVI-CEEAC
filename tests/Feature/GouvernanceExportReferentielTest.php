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
 * P0 — Gouvernance unifiée, généralisation au Référentiel financier : l'archivage des exports Excel
 * (sources/partenaires/conventions) et PDF (synthèse/sources/PTF) produit un rapport officiel
 * traçable via {@see GouvernanceExportService}, sans altérer les exports rapides existants.
 */
class GouvernanceExportReferentielTest extends TestCase
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

    private function exportateur(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo([
            'source_financement.exporter', 'partenaire_financier.exporter',
            'convention_financement.exporter', 'nomenclature.generer_pdf',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_archive_excel_sources_global(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('referentiel.export.archiver', 'sources'))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_referentiel_sources')->firstOrFail();
        $this->assertSame('excel', $rapport->format);
        $this->assertNull($rapport->exercice_id); // sources = référentiel global
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.xlsx', $rapport->chemin);
    }

    public function test_archive_excel_conventions_par_exercice(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('referentiel.export.archiver', ['cle' => 'conventions', 'exercice' => $this->exercice->id]))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_referentiel_conventions')->firstOrFail();
        $this->assertSame('excel', $rapport->format);
        $this->assertSame($this->exercice->id, $rapport->exercice_id);
    }

    public function test_archive_pdf_synthese(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('referentiel.pdf.archiver', ['cle' => 'synthese', 'exercice' => $this->exercice->id]))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_referentiel_synthese_pdf')->firstOrFail();
        $this->assertSame('pdf', $rapport->format);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.pdf', $rapport->chemin);
    }

    public function test_archive_refuse_sans_permission(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true]))
            ->get(route('referentiel.export.archiver', 'sources'))
            ->assertForbidden();

        $this->assertSame(0, Rapport::count());
    }

    public function test_cle_inconnue_retourne_404(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('referentiel.export.archiver', 'inexistant'))
            ->assertNotFound();

        $this->assertSame(0, Rapport::count());
    }

    public function test_export_rapide_existant_reste_disponible(): void
    {
        $this->actingAs($this->exportateur())
            ->get(route('referentiel.export.sources'))
            ->assertOk();

        $this->assertSame(0, Rapport::count());
    }
}
