<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use App\Services\Reporting\ReportingService;
use App\Services\Reporting\SignatureService;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportingModuleTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SafeDatabaseSeeder::class);
        $this->exercice = Exercice::firstWhere('annee', 2026);
        $this->admin = User::firstWhere('email', 'administrateur@ceeac.test');
    }

    public function test_hub_cockpit_et_apercus_accessibles(): void
    {
        $this->actingAs($this->admin)->get(route('reporting.index'))->assertOk()->assertSee('Reporting & Analyse');
        $this->actingAs($this->admin)->get(route('reporting.cockpit', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('Tableau de bord exécutif');

        foreach (array_keys(\App\Services\Reporting\RapportComposer::TYPES) as $type) {
            $this->actingAs($this->admin)
                ->get(route('reporting.show', ['type' => $type, 'exercice' => $this->exercice->id]))
                ->assertOk();
        }
    }

    public function test_generation_pdf_excel_word_historise_et_signe(): void
    {
        Storage::fake('local');

        $formats = [
            'pdf' => 'application/pdf',
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'word' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        foreach ($formats as $format => $mime) {
            $resp = $this->actingAs($this->admin)->get(route('reporting.generer', [
                'type' => 'execution_axe', 'format' => $format, 'exercice' => $this->exercice->id,
            ]));
            $resp->assertOk();
            $this->assertStringContainsString($mime, $resp->headers->get('content-type'));
        }

        $rapportsGeneres = Rapport::where('type', 'execution_axe')->whereIn('format', array_keys($formats));
        $this->assertSame(3, $rapportsGeneres->count());
        $rapport = $rapportsGeneres->first();
        $this->assertNotEmpty($rapport->hash);
        $this->assertNotEmpty($rapport->qr_token);
        $this->assertSame('genere', $rapport->statut);
        Storage::disk('local')->assertExists($rapport->chemin);

        // Re-générer le même rapport incrémente la version (versioning)
        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'execution_axe', 'format' => 'pdf', 'exercice' => $this->exercice->id,
        ]))->assertOk();
        $versions = Rapport::where('type', 'execution_axe')->where('format', 'pdf')->orderBy('version')->pluck('version');
        $this->assertEquals([1, 2], $versions->all());

        // Signature électronique vérifiable
        $sign = app(SignatureService::class);
        $rapport->refresh();
        $rapport->signature = $sign->signer($rapport, $this->admin);
        $rapport->signe_par = $this->admin->id;
        $rapport->signe_le = now();
        $rapport->statut = 'signe';
        $rapport->save();
        $this->assertTrue($sign->verifier($rapport->refresh()));

        // Altération du hash → signature invalide
        $rapport->hash = str_repeat('0', 64);
        $this->assertFalse($sign->verifier($rapport));
    }

    public function test_verification_publique_qr(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'pap', 'format' => 'pdf', 'exercice' => $this->exercice->id,
        ]))->assertOk();

        $rapport = Rapport::first();

        // Accessible sans authentification
        $this->get($rapport->urlVerification())->assertOk()
            ->assertSee($rapport->reference)->assertSee('authentique', false);

        // Jeton inconnu → rapport introuvable
        $this->get(route('verification.rapport', 'jeton-bidon'))->assertOk()->assertSee('introuvable');
    }

    public function test_reconciliation_stricte_de_la_chaine(): void
    {
        $svc = app(ReportingService::class);
        $g = $svc->global($this->exercice->id);

        // Σ(par axe) == global (réconciliation par reliquat)
        $axes = $svc->parAxe($this->exercice->id);
        $this->assertEqualsWithDelta($g['ouvert'], $axes->sum('ouvert'), 0.01);
        $this->assertEqualsWithDelta($g['engage'], $axes->sum('engage'), 0.01);
        $this->assertEqualsWithDelta($g['paye'], $axes->sum('paye'), 0.01);

        // Σ(par catégorie) == global
        $cat = $svc->parCategorie($this->exercice->id);
        $this->assertEqualsWithDelta($g['ouvert'], $cat->sum('ouvert'), 0.01);
        $this->assertEqualsWithDelta($g['paye'], $cat->sum('paye'), 0.01);

        // Σ(par bailleur) == global
        $bail = $svc->parBailleur($this->exercice->id);
        $this->assertEqualsWithDelta($g['ouvert'], $bail['sources']->sum('ouvert'), 0.01);
        $this->assertEqualsWithDelta($g['paye'], $bail['sources']->sum('paye'), 0.01);

        // Invariant chaîne : payé ≤ ordonnancé ≤ liquidé ≤ engagé ≤ ouvert
        $this->assertGreaterThanOrEqual($g['paye'], $g['ordonnance']);
        $this->assertGreaterThanOrEqual($g['ordonnance'], $g['liquide']);
        $this->assertGreaterThanOrEqual($g['liquide'], $g['engage']);
        $this->assertGreaterThanOrEqual($g['engage'], $g['ouvert']);
    }

    public function test_acces_protege(): void
    {
        // Non authentifié → redirection login
        $this->get(route('reporting.index'))->assertRedirect(route('login'));
    }
}
