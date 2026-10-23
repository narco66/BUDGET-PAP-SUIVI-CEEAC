<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use App\Services\Reporting\RapportComposer;
use App\Services\Reporting\RapportPeriodiqueService;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase P1 — rapports institutionnels trimestriel, semestriel et annuel. */
class ReportingPeriodiqueTest extends TestCase
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

    public function test_apercus_rapports_periodiques_accessibles(): void
    {
        foreach (['trimestriel', 'semestriel', 'annuel'] as $type) {
            $this->actingAs($this->admin)
                ->get(route('reporting.show', [
                    'type' => $type,
                    'exercice' => $this->exercice->id,
                    'periode' => $type === 'annuel' ? '2026' : ($type === 'trimestriel' ? '2026-T1' : '2026-S1'),
                ]))
                ->assertOk()
                ->assertSee(RapportComposer::TYPES[$type], false);
        }
    }

    public function test_generation_trimestriel_pdf_avec_periode(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'trimestriel',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
            'periode' => '2026-T1',
        ]))->assertOk();

        $rapport = Rapport::where('type', 'trimestriel')->first();
        $this->assertSame('2026-T1', $rapport->periode);
        $this->assertSame('trimestriel', $rapport->periode_type);
        $this->assertGreaterThanOrEqual(5, count(
            app(RapportComposer::class)->composer('trimestriel', $this->exercice, ['periode' => '2026-T1'])['sections']
        ));
    }

    public function test_generation_semestriel_excel(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'semestriel',
            'format' => 'excel',
            'exercice' => $this->exercice->id,
            'periode' => '2026-S1',
        ]))->assertOk();

        $rapport = Rapport::where('type', 'semestriel')->first();
        $this->assertSame('2026-S1', $rapport->periode);
        $composed = app(RapportComposer::class)->composer('semestriel', $this->exercice, ['periode' => '2026-S1']);
        $titres = collect($composed['sections'])->pluck('titre');
        $this->assertTrue($titres->contains('Analyse comparative S1 / S2'));
    }

    public function test_generation_annuel_word(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'annuel',
            'format' => 'word',
            'exercice' => $this->exercice->id,
            'periode' => '2026',
        ]))->assertOk();

        $rapport = Rapport::where('type', 'annuel')->first();
        $this->assertSame('annuel', $rapport->periode_type);
        $this->assertSame('word', $rapport->format);
    }

    public function test_commande_generer_trimestre(): void
    {
        Storage::fake('local');

        $this->assertSame(0, Artisan::call('reporting:generer-trimestre', [
            '--exercice' => $this->exercice->annee,
            '--periode' => '2026-T2',
            '--format' => 'pdf',
        ]));
        $this->assertDatabaseHas('rapports', [
            'type' => 'trimestriel',
            'periode' => '2026-T2',
            'format' => 'pdf',
        ]);
    }

    public function test_commande_generer_semestre(): void
    {
        Storage::fake('local');

        Artisan::call('reporting:generer-semestre', [
            '--exercice' => $this->exercice->id,
            '--periode' => '2026-S2',
            '--format' => 'csv',
        ]);

        $this->assertDatabaseHas('rapports', [
            'type' => 'semestriel',
            'periode' => '2026-S2',
            'format' => 'csv',
        ]);
    }

    public function test_commande_generer_annuel(): void
    {
        Storage::fake('local');

        Artisan::call('reporting:generer-annuel', [
            '--exercice' => 2026,
            '--format' => 'pdf',
        ]);

        $this->assertDatabaseHas('rapports', [
            'type' => 'annuel',
            'periode' => '2026',
            'format' => 'pdf',
        ]);
    }

    public function test_sections_trimestriel_contiennent_volets(): void
    {
        $sections = app(RapportPeriodiqueService::class)
            ->sectionsTrimestriel($this->exercice, ['periode' => '2026-T1']);
        $titres = collect($sections)->pluck('titre');

        $this->assertTrue($titres->contains('Volet stratégique — exécution par axe'));
        $this->assertTrue($titres->contains('Volet budgétaire'));
        $this->assertTrue($titres->contains('Volet physique — campagnes de suivi'));
        $this->assertTrue($titres->contains('Réalisations majeures'));
    }
}
