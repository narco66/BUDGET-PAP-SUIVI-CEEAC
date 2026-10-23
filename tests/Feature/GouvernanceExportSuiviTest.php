<?php

namespace Tests\Feature;

use App\Models\CampagneSuivi;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0 — Gouvernance unifiée, généralisation au module Suivi : l'archivage de l'état des collectes
 * d'une campagne (PDF / Excel / CSV) produit un rapport officiel traçable via
 * {@see GouvernanceExportService}, sans altérer les exports rapides existants.
 */
class GouvernanceExportSuiviTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private CampagneSuivi $campagne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SuiviPermissionsSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->campagne = CampagneSuivi::create([
            'exercice_id' => $this->exercice->id,
            'libelle' => 'Campagne T1',
            'type' => 'trimestriel',
            'periode' => '2026-T1',
            'statut' => 'ouverte',
        ]);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_archive_collectes_excel(): void
    {
        // « chef_service » a suivi_physique.export + *.voir, sans ged.creer.
        $this->actingAs($this->user('chef_service'))
            ->get(route('suivi.export.collectes.archiver', [$this->campagne, 'xlsx']))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_suivi_collectes')->firstOrFail();
        $this->assertSame('excel', $rapport->format);
        $this->assertSame($this->exercice->id, $rapport->exercice_id);
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertStringEndsWith('.xlsx', $rapport->chemin);
    }

    public function test_archive_collectes_csv(): void
    {
        $this->actingAs($this->user('chef_service'))
            ->get(route('suivi.export.collectes.archiver', [$this->campagne, 'csv']))
            ->assertRedirect();

        $this->assertDatabaseHas('rapports', ['type' => 'export_suivi_collectes', 'format' => 'csv']);
    }

    public function test_archive_collectes_pdf(): void
    {
        $this->actingAs($this->user('chef_service'))
            ->get(route('suivi.export.collectes.archiver', [$this->campagne, 'pdf']))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_suivi_collectes_pdf')->firstOrFail();
        $this->assertSame('pdf', $rapport->format);
        $this->assertStringEndsWith('.pdf', $rapport->chemin);
    }

    public function test_archive_refuse_sans_permission(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->get(route('suivi.export.collectes.archiver', [$this->campagne, 'xlsx']))
            ->assertForbidden();

        $this->assertSame(0, Rapport::count());
    }

    public function test_export_rapide_existant_reste_disponible(): void
    {
        $this->actingAs($this->user('chef_service'))
            ->get(route('suivi.export.collectes', [$this->campagne, 'xlsx']))
            ->assertOk();

        $this->assertSame(0, Rapport::count());
    }
}
