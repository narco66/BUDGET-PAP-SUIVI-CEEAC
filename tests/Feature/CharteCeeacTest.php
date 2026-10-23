<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use App\Services\Reporting\RapportComposer;
use App\Services\Reporting\RapportRenderer;
use App\Support\CharteCeeac;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** P3-8 — Charte graphique CEEAC (exports institutionnels). */
class CharteCeeacTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(SafeDatabaseSeeder::class);
        $this->exercice = Exercice::firstWhere('annee', 2026);
        $this->admin = User::firstWhere('email', 'administrateur@ceeac.test');
    }

    public function test_config_charte_coherente(): void
    {
        $this->assertSame('#0a4a8f', CharteCeeac::primaire());
        $this->assertSame('0A4A8F', CharteCeeac::primaireHex());
        $this->assertStringContainsString('CEEAC', CharteCeeac::institution());
        $this->assertNotEmpty(CharteCeeac::export()['mention_pied']);
    }

    public function test_pdf_contient_identite_institutionnelle(): void
    {
        Storage::fake('local');

        $composed = app(RapportComposer::class)->composer('pap', $this->exercice);
        $html = view('reporting.pdf.document', [
            'c' => $composed,
            'meta' => [
                'reference' => 'RPT-2026-000001',
                'version' => 1,
                'date' => now()->format('d/m/Y H:i'),
                'auteur' => $this->admin->name,
                'verification' => route('verification.rapport', 'token-test'),
                'signataire' => null,
                'signe_le' => null,
                'qr' => null,
            ],
            'charte' => CharteCeeac::export(),
        ])->render();

        $this->assertStringContainsString(CharteCeeac::institution(), $html);
        $this->assertStringContainsString(CharteCeeac::primaire(), $html);

        $result = app(RapportRenderer::class)->generer('pap', $this->exercice, 'pdf', [], $this->admin);
        $this->assertStringStartsWith('%PDF', $result['contenu']);
    }

    public function test_word_contient_charte(): void
    {
        $composed = app(RapportComposer::class)->composer('pap', $this->exercice);
        $contenu = app(\App\Services\Reporting\WordRenderer::class)->rendre($composed, [
            'reference' => 'RPT-TEST',
            'version' => 1,
            'date' => now()->format('d/m/Y H:i'),
            'auteur' => 'Test',
            'verification' => 'http://test/verify',
            'signataire' => null,
            'signe_le' => null,
        ], null);

        $this->assertNotEmpty($contenu);
        $this->assertStringStartsWith('PK', $contenu);
    }

    public function test_pptx_contient_charte(): void
    {
        $composed = app(RapportComposer::class)->composer('execution_synthese', $this->exercice);
        $contenu = app(\App\Services\Reporting\PptxRenderer::class)->rendre($composed, [
            'reference' => 'RPT-TEST',
            'version' => 1,
            'date' => now()->format('d/m/Y H:i'),
            'verification' => 'http://test/verify',
        ], null);

        $this->assertNotEmpty($contenu);
        $this->assertStringStartsWith('PK', $contenu);
    }
}
