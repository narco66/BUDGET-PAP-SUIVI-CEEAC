<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0 — Gouvernance unifiée, généralisation au module Recettes : l'archivage des états recettes
 * (PDF / Excel / Word) produit un rapport officiel traçable via {@see GouvernanceExportService},
 * sans altérer les exports rapides existants.
 */
class GouvernanceExportRecettesTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RecettesPermissionsSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function archive(string $cle, string $format): string
    {
        // « comptable » possède recette.exporter sans ged.creer : chemin ciblé sans GED.
        $this->actingAs($this->user('comptable'))
            ->get(route('recettes.rapports.archiver', ['cle' => $cle, 'format' => $format, 'exercice' => $this->exercice->id]))
            ->assertRedirect();

        return $cle;
    }

    public function test_archive_excel_encaissements(): void
    {
        $this->archive('encaissements', 'excel');

        $rapport = Rapport::where('type', 'export_recettes_encaissements')->firstOrFail();
        $this->assertSame('excel', $rapport->format);
        $this->assertSame($this->exercice->id, $rapport->exercice_id);
        $this->assertSame('genere', $rapport->statut_workflow);
        $this->assertNotEmpty($rapport->hash);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.xlsx', $rapport->chemin);
    }

    public function test_archive_word_rapport_financier(): void
    {
        $this->archive('rapport-financier', 'word');

        $rapport = Rapport::where('type', 'export_recettes_financier')->firstOrFail();
        $this->assertSame('word', $rapport->format);
        $this->assertSame('Rapport financier des recettes', $rapport->titre);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.docx', $rapport->chemin);
    }

    public function test_archive_pdf_mobilisation(): void
    {
        $this->archive('mobilisation', 'pdf');

        $rapport = Rapport::where('type', 'export_recettes_mobilisation')->firstOrFail();
        $this->assertSame('pdf', $rapport->format);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.pdf', $rapport->chemin);
    }

    public function test_archive_refuse_sans_permission(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->get(route('recettes.rapports.archiver', ['cle' => 'encaissements', 'format' => 'excel', 'exercice' => $this->exercice->id]))
            ->assertForbidden();

        $this->assertSame(0, Rapport::count());
    }

    public function test_format_non_autorise_retourne_404(): void
    {
        // encaissements n'autorise pas le PDF.
        $this->actingAs($this->user('comptable'))
            ->get(route('recettes.rapports.archiver', ['cle' => 'encaissements', 'format' => 'pdf', 'exercice' => $this->exercice->id]))
            ->assertNotFound();

        $this->assertSame(0, Rapport::count());
    }

    public function test_export_rapide_existant_reste_disponible(): void
    {
        $this->actingAs($this->user('comptable'))
            ->get(route('recettes.rapports.mobilisation.csv', ['exercice' => $this->exercice->id]))
            ->assertOk();

        $this->assertSame(0, Rapport::count());
    }
}
