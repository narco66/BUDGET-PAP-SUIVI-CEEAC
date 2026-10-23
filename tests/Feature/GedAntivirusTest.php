<?php

namespace Tests\Feature;

use App\Services\Ged\Antivirus\TestAntivirusScanner;
use App\Services\Ged\GedAntivirusService;
use App\Services\Ged\GedDocumentService;
use App\Services\Ged\GedStorageService;
use Database\Seeders\GedSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** P3-9 — Antivirus GED (ClamAV / quarantaine). */
class GedAntivirusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
        $this->seed(GedSeeder::class);
        Storage::fake('local');
        $this->app->forgetInstance(\App\Contracts\Ged\GedAntivirusScannerInterface::class);
        $this->app->forgetInstance(GedAntivirusService::class);
        $this->app->forgetInstance(GedStorageService::class);
    }

    public function test_statut_inactif_par_defaut(): void
    {
        config(['ged.antivirus.active' => false]);

        $statut = app(GedAntivirusService::class)->statut();

        $this->assertFalse($statut['actif']);
        $this->assertFalse($statut['protection_effective']);
    }

    public function test_rejette_fichier_infecte_et_quarantaine(): void
    {
        config([
            'ged.antivirus.active' => true,
            'ged.antivirus.driver' => 'test',
            'ged.antivirus.quarantine' => true,
        ]);

        $fichier = UploadedFile::fake()->createWithContent(
            'malware.pdf',
            'contenu '.TestAntivirusScanner::MARQUEUR,
            'application/pdf'
        );

        try {
            app(GedStorageService::class)->stocker($fichier);
            $this->fail('Une exception antivirus était attendue.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('analyse antivirus', $e->getMessage());
        }

        $this->assertNotEmpty(Storage::disk('local')->allFiles('ged/quarantine'));
        $this->assertEmpty(array_filter(
            Storage::disk('local')->allFiles(),
            fn (string $path) => str_contains($path, 'malware.pdf') && ! str_starts_with($path, 'ged/quarantine/')
        ));
    }

    public function test_fichier_propre_accepte(): void
    {
        config([
            'ged.antivirus.active' => true,
            'ged.antivirus.driver' => 'test',
        ]);

        $fichier = UploadedFile::fake()->create('propre.pdf', 50, 'application/pdf');
        $resultat = app(GedStorageService::class)->stocker($fichier);

        $this->assertTrue(Storage::disk('local')->exists($resultat['chemin']));
    }

    public function test_depot_ged_bloque_si_infecte(): void
    {
        config([
            'ged.antivirus.active' => true,
            'ged.antivirus.driver' => 'test',
        ]);

        $user = \App\Models\User::factory()->create(['actif' => true])->assignRole('directeur');
        $fichier = UploadedFile::fake()->createWithContent(
            'virus.pdf',
            TestAntivirusScanner::MARQUEUR,
            'application/pdf'
        );

        $this->expectException(\RuntimeException::class);

        app(GedDocumentService::class)->deposer($fichier, [
            'titre' => 'Fichier infecté',
            'module_source' => 'GED',
            'type_code' => 'DOC',
        ], $user);
    }

    public function test_parametres_affichent_statut_antivirus(): void
    {
        config(['ged.antivirus.active' => true, 'ged.antivirus.driver' => 'test']);

        $admin = \App\Models\User::factory()->create(['actif' => true])->assignRole('administrateur');

        $this->actingAs($admin)->get(route('ged.parametres'))
            ->assertOk()
            ->assertSee('Antivirus (ClamAV)', false)
            ->assertSee('test', false);
    }
}
