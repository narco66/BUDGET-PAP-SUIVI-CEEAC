<?php

namespace Tests\Feature;

use App\Contracts\Ged\GedSignatureProviderInterface;
use App\Enums\Ged\SignatureNiveau;
use App\Services\Ged\GedDocumentService;
use App\Services\Ged\GedSignatureService;
use App\Services\Ged\Providers\TestSignatureProvider;
use Database\Seeders\GedSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** P3-6 — Signature qualifiée GED (eIDAS). */
class GedSignatureQualifieeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
        $this->seed(GedSeeder::class);
        Storage::fake('local');
        $this->rebindSignatureProvider();
    }

    private function rebindSignatureProvider(): void
    {
        $this->app->forgetInstance(GedSignatureProviderInterface::class);
        $this->app->forgetInstance(GedSignatureService::class);
    }

    private function deposerDocument(): \App\Models\Ged\GedDocument
    {
        $createur = \App\Models\User::factory()->create(['actif' => true])->assignRole('directeur');

        return app(GedDocumentService::class)->deposer(
            UploadedFile::fake()->create('contrat.pdf', 80, 'application/pdf'),
            ['titre' => 'Contrat à signer', 'module_source' => 'GED', 'type_code' => 'DOC'],
            $createur
        );
    }

    public function test_statut_interne_par_defaut(): void
    {
        config(['ged.signature.provider' => 'interne']);
        $this->rebindSignatureProvider();

        $statut = app(GedSignatureService::class)->statut();

        $this->assertSame('interne', $statut['provider']);
        $this->assertFalse($statut['qualification_eidas']);
    }

    public function test_signature_qualifiee_avec_driver_test(): void
    {
        config(['ged.signature.provider' => 'test']);
        $this->rebindSignatureProvider();

        $createur = \App\Models\User::factory()->create(['actif' => true])->assignRole('directeur');
        $signataire = \App\Models\User::factory()->create(['actif' => true])->assignRole('controleur_financier');
        $doc = app(GedDocumentService::class)->deposer(
            UploadedFile::fake()->create('acte.pdf', 60, 'application/pdf'),
            ['titre' => 'Acte qualifié'],
            $createur
        );

        $service = app(GedSignatureService::class);
        $signature = $service->demander($doc, $createur, $signataire);
        $service->signer($signature, $signataire);

        $signature->refresh();
        $doc->refresh();

        $this->assertTrue($doc->est_signe);
        $this->assertSame('test', $signature->provider);
        $this->assertSame(SignatureNiveau::Qualifiee->value, $signature->niveau);
        $this->assertNotNull($signature->externe_id);
        $this->assertNotNull($signature->horodatage_tsa);
        $this->assertStringContainsString(
            TestSignatureProvider::MARQUEUR_QUALIFIE,
            json_encode($signature->metadonnees_prestataire)
        );
    }

    public function test_verification_publique_affiche_qualifiee(): void
    {
        config(['ged.signature.provider' => 'test']);
        $this->rebindSignatureProvider();

        $createur = \App\Models\User::factory()->create(['actif' => true])->assignRole('directeur');
        $signataire = \App\Models\User::factory()->create(['actif' => true])->assignRole('controleur_financier');
        $doc = $this->deposerDocument();

        $service = app(GedSignatureService::class);
        $signature = $service->demander($doc, $createur, $signataire);
        $service->signer($signature, $signataire);

        $this->get(route('verification.ged', $doc->uuid))
            ->assertOk()
            ->assertSee('Qualifiée', false)
            ->assertSee('Qualification eIDAS', false);
    }

    public function test_provider_externe_avec_http_fake(): void
    {
        config([
            'ged.signature.provider' => 'externe',
            'ged.signature.externe.url' => 'https://qtsp.test/api',
            'ged.signature.externe.api_key' => 'secret',
            'ged.signature.externe.niveau' => 'qualifiee',
        ]);
        $this->rebindSignatureProvider();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_ends_with($request->url(), '/demandes')) {
                return Http::response(['id' => 'EXT-QTSP-001'], 201);
            }
            if (str_ends_with($request->url(), '/signer')) {
                return Http::response([
                    'empreinte' => hash('sha256', 'certificat-test'),
                    'niveau' => 'qualifiee',
                    'horodatage_tsa' => now()->toIso8601String(),
                    'token_verification' => 'tok-test',
                ], 200);
            }
            if (str_contains($request->url(), '/verifier/')) {
                return Http::response(['niveau' => 'qualifiee', 'valide' => true]);
            }

            return Http::response([], 404);
        });

        $createur = \App\Models\User::factory()->create(['actif' => true])->assignRole('directeur');
        $signataire = \App\Models\User::factory()->create(['actif' => true])->assignRole('controleur_financier');
        $doc = $this->deposerDocument();

        $service = app(GedSignatureService::class);
        $signature = $service->demander($doc, $createur, $signataire);

        $this->assertSame('externe', $signature->provider);
        $this->assertSame('EXT-QTSP-001', $signature->externe_id);

        $service->signer($signature, $signataire);
        $signature->refresh();

        $this->assertSame('signe', $signature->statut);
        $this->assertSame('externe', $signature->provider);
        $this->assertSame(SignatureNiveau::Qualifiee->value, $signature->niveau);

        $this->assertDatabaseHas('ged_signatures', [
            'id' => $signature->id,
            'statut' => 'signe',
            'provider' => 'externe',
            'niveau' => 'qualifiee',
        ]);

        $verification = app(GedSignatureService::class)->verifier(
            \App\Models\Ged\GedDocument::findOrFail($doc->id)
        );
        $this->assertTrue($verification['qualification_eidas']);
        $this->assertSame('externe', $verification['provider']);
    }

    public function test_fallback_interne_sans_url_externe(): void
    {
        config([
            'ged.signature.provider' => 'externe',
            'ged.signature.externe.url' => null,
        ]);
        $this->rebindSignatureProvider();

        $createur = \App\Models\User::factory()->create(['actif' => true])->assignRole('directeur');
        $signataire = \App\Models\User::factory()->create(['actif' => true])->assignRole('controleur_financier');
        $doc = $this->deposerDocument();

        $service = app(GedSignatureService::class);
        $signature = $service->demander($doc, $createur, $signataire);
        $service->signer($signature, $signataire);

        $signature->refresh();
        $this->assertSame('interne', $signature->provider);
        $this->assertSame(SignatureNiveau::Interne->value, $signature->niveau);
    }

    public function test_parametres_affichent_statut_signature(): void
    {
        config(['ged.signature.provider' => 'test']);
        $this->rebindSignatureProvider();

        $admin = \App\Models\User::factory()->create(['actif' => true])->assignRole('administrateur');

        $this->actingAs($admin)->get(route('ged.parametres'))
            ->assertOk()
            ->assertSee('Signature électronique (eIDAS)', false)
            ->assertSee('Qualifiée', false)
            ->assertSee('test', false);
    }
}
