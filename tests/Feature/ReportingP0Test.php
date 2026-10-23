<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use App\Services\Budget\SuiviFinancierService;
use App\Services\Reporting\RapportComposer;
use App\Services\Reporting\ReportingService;
use App\Services\Rbm\IndicateurPerformanceService;
use App\Services\Suivi\SuiviEvaluationService;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase P0 Reporting — période, permissions, CSV, verrou, cohérence SF/SI/SE. */
class ReportingP0Test extends TestCase
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

    public function test_periode_trimestrielle_enregistree(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'execution_synthese',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
            'periode' => '2026-T2',
        ]))->assertOk();

        $rapport = Rapport::where('type', 'execution_synthese')->latest()->first();
        $this->assertSame('2026-T2', $rapport->periode);
        $this->assertSame('trimestriel', $rapport->periode_type);
        $this->assertSame('2026-04-01', $rapport->periode_debut->toDateString());
        $this->assertSame('2026-06-30', $rapport->periode_fin->toDateString());
        $this->assertNotEmpty($rapport->checksum_sources);
    }

    public function test_periode_invalide_rejetee(): void
    {
        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'pap',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
            'periode' => '2025-T1',
        ]))->assertStatus(422);
    }

    public function test_alias_reporting_view_pour_commissaire(): void
    {
        $commissaire = User::firstWhere('email', 'commissaire@ceeac.test');
        $this->assertTrue($commissaire->can('reporting.view'));
        $this->assertTrue($commissaire->can('reporting.generate'));

        $this->actingAs($commissaire)
            ->get(route('reporting.index', ['exercice' => $this->exercice->id]))
            ->assertOk();
    }

    public function test_export_csv_disponible(): void
    {
        Storage::fake('local');

        $resp = $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'execution_axe',
            'format' => 'csv',
            'exercice' => $this->exercice->id,
            'periode' => '2026',
        ]));

        $resp->assertOk();
        $this->assertStringContainsString('text/csv', $resp->headers->get('content-type'));
        $this->assertSame(1, Rapport::where('format', 'csv')->count());
    }

    public function test_rapport_signe_est_verrouille(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'pap',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]))->assertOk();

        $rapport = Rapport::first();

        $this->actingAs($this->admin)
            ->post(route('reporting.signer', $rapport))
            ->assertRedirect();

        $rapport->refresh();
        $this->assertTrue($rapport->verrouille);
        $this->assertSame('signe', $rapport->statut);

        $this->expectException(\RuntimeException::class);
        $rapport->update(['titre' => 'Modification interdite']);
    }

    public function test_coherence_situation_budgetaire_suivi_financier(): void
    {
        $global = app(ReportingService::class)->global($this->exercice->id);
        $hubBudget = app(SuiviFinancierService::class)->hub($this->exercice->id)['budget'];
        $composed = app(RapportComposer::class)->composer('situation_budgetaire', $this->exercice);

        $this->assertEqualsWithDelta($global['taux_execution'], $hubBudget['taux_execution'], 0.01);
        $this->assertEqualsWithDelta(
            $global['taux_execution'],
            $this->kpiValue($composed, "Taux d'exécution"),
            0.01
        );
        $this->assertEqualsWithDelta(
            $global['reste_a_engager'],
            $this->kpiValue($composed, 'Restes à engager'),
            0.01
        );
    }

    public function test_coherence_indicateurs_evolution_hub(): void
    {
        $perf = app(IndicateurPerformanceService::class);
        $feux = $perf->feuxGlobaux($this->exercice->id);
        $composed = app(RapportComposer::class)->composer('indicateurs_evolution', $this->exercice);

        $this->assertSame($feux['vert'], (int) $this->kpiValue($composed, 'Vert'));
        $this->assertSame($feux['rouge'], (int) $this->kpiValue($composed, 'Rouge'));
    }

    public function test_coherence_suivi_evaluation_rapport(): void
    {
        $svc = app(SuiviEvaluationService::class);
        $donnees = $svc->rapportDonnees($this->exercice->id);
        $composed = app(RapportComposer::class)->composer('suivi_evaluation', $this->exercice);

        $this->assertEqualsWithDelta(
            $donnees['scores_courants']['score_global'],
            $this->kpiValue($composed, 'Score global'),
            0.01
        );
    }

    /** @param array<string, mixed> $composed */
    private function kpiValue(array $composed, string $label): float|int|null
    {
        foreach ($composed['sections'] as $section) {
            if (($section['type'] ?? '') !== 'kpi') {
                continue;
            }
            foreach ($section['items'] as $item) {
                if (($item['label'] ?? '') === $label) {
                    return $item['valeur'];
                }
            }
        }

        return null;
    }
}
