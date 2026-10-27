<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\User;
use App\Services\Strategie\StrategieAlignementService;
use App\Services\Strategie\StrategiePapLiaisonService;
use App\Services\Strategie\StrategiePilotageService;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\Pap2026Seeder;
use Database\Seeders\PlanificationStrategiqueSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StrategiePermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase P4 — liaison programmation ↔ PAP, pilotage décisionnel, alignement bout-en-bout. */
class PlanificationStrategiquePhaseP4Test extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            StrategiePermissionsSeeder::class,
            ExerciceSeeder::class,
            WorkflowSeeder::class,
            Pap2026Seeder::class,
        ]);
        $this->gestionnaire = User::factory()->create(['actif' => true]);
        $this->gestionnaire->assignRole('directeur');
        $this->seed(PlanificationStrategiqueSeeder::class);
    }

    public function test_migration_liaison_pap_programmation(): void
    {
        $this->assertTrue(Schema::hasColumn('programmations_annuelles', 'pap_id'));
    }

    public function test_seeder_lie_programmation_au_pap_2026(): void
    {
        $prog = ProgrammationAnnuelle::whereHas('exercice', fn ($q) => $q->where('annee', 2026))->first();
        $pap = Pap::whereHas('exercice', fn ($q) => $q->where('annee', 2026))->first();

        $this->assertNotNull($prog);
        $this->assertNotNull($pap);
        $this->assertSame($pap->id, $prog->pap_id);
    }

    public function test_service_synchroniser_liaisons_pap(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        $prog = ProgrammationAnnuelle::where('exercice_id', $exercice->id)->first();
        $prog->update(['pap_id' => null]);

        $this->actingAs($this->gestionnaire);
        $count = app(StrategiePapLiaisonService::class)->synchroniserLiaisons($exercice->id);

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertNotNull($prog->fresh()->pap_id);
    }

    public function test_pont_pap_accessible(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.pont-pap'))
            ->assertOk()
            ->assertSee('Pont Programmation ↔ PAP');
    }

    public function test_dashboard_decisionnel_accessible(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.dashboard.decisionnel'))
            ->assertOk()
            ->assertSee('Tableau de pilotage décisionnel')
            ->assertSee('Score de pilotage')
            ->assertSee('Composantes du score de pilotage')
            ->assertSee('Points d\'attention', false);
    }

    public function test_score_ignore_les_composantes_sans_base_et_coherence_non_evaluable_si_vide(): void
    {
        // Référentiel stratégique vide : la cohérence n'est pas « 75 % » mais non évaluable.
        \App\Models\Strategie\PlanStrategique::query()->delete();
        \App\Models\Strategie\ObjectifStrategique::query()->delete();
        $coherence = app(\App\Services\Strategie\StrategieCoherenceService::class)->rapport();
        if (\App\Models\Strategie\IndicateurStrategique::query()->count() === 0 && \App\Models\Strategie\RisqueStrategique::query()->count() === 0
            && app(\App\Services\Strategie\StrategieAlignementService::class)->syntheseAlignement()['lignes_matrice'] === 0) {
            $this->assertFalse($coherence['score_evaluable']);
        }
        foreach ($coherence['controles'] as $c) {
            $this->assertSame($c['base'] === 0, $c['taux'] === null, 'Un contrôle sans base n\'a pas de taux.');
        }

        $tableau = app(StrategiePilotageService::class)->tableauDecisionnel(Exercice::firstWhere('annee', 2026)->id);
        $evaluables = collect($tableau['score_pilotage_detail'])->pluck('taux')->filter(fn ($t) => $t !== null);
        $this->assertEqualsWithDelta($evaluables->isEmpty() ? 0.0 : round($evaluables->avg(), 1), $tableau['score_pilotage'], 0.05);
        $this->assertCount(5, $tableau['score_pilotage_detail'], 'Le taux de la matrice n\'est compté qu\'une fois.');
    }

    public function test_service_pilotage_retourne_score(): void
    {
        $tableau = app(StrategiePilotageService::class)->tableauDecisionnel(
            Exercice::firstWhere('annee', 2026)->id
        );

        $this->assertArrayHasKey('score_pilotage', $tableau);
        $this->assertArrayHasKey('reconciliation', $tableau);
        $this->assertGreaterThan(0, $tableau['score_pilotage']);
    }

    public function test_reconciliation_programmation_pap(): void
    {
        $reconciliation = app(StrategiePapLiaisonService::class)->reconciliation(
            Exercice::firstWhere('annee', 2026)->id
        );

        $this->assertNotNull($reconciliation['pap']);
        $this->assertSame(100.0, $reconciliation['taux_liaison_prog']);
        $this->assertArrayHasKey('execution_pap', $reconciliation);
    }

    public function test_chaine_alignement_strategie_rbm_pap(): void
    {
        $programme = ProgrammeStrategique::first();
        $projet = ProjetStrategique::first();
        $pilier = Pilier::first();

        $this->assertNotNull($programme);
        $this->assertNotNull($projet);
        $this->assertNotNull($pilier);

        $lignes = app(StrategieAlignementService::class)->matriceAlignement();
        $this->assertTrue($lignes->contains(fn ($l) => $l['programme'] === 'PS-INT-ECO'));

        $this->actingAs($this->gestionnaire)->put(route('strategie.pont-rbm.update', $pilier), [
            'orientation_strategique_id' => $programme->orientation_strategique_id,
            'programme_strategique_id' => $programme->id,
        ])->assertRedirect();

        $this->assertSame($programme->orientation_strategique_id, $pilier->fresh()->orientation_strategique_id);
    }

    public function test_synchronisation_pont_pap_via_route(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        ProgrammationAnnuelle::where('exercice_id', $exercice->id)->update(['pap_id' => null]);

        $this->actingAs($this->gestionnaire)
            ->post(route('strategie.pont-pap.synchroniser'), ['exercice' => $exercice->id])
            ->assertRedirect();

        $this->assertNotNull(ProgrammationAnnuelle::where('exercice_id', $exercice->id)->first()->pap_id);
    }

    public function test_export_pilotage_pdf(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.export.pilotage.pdf', ['exercice' => Exercice::firstWhere('annee', 2026)->id]))
            ->assertOk();
    }

    public function test_hub_phase_p4_liens(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.hub'))
            ->assertOk()
            ->assertSee('Pilotage');
    }
}
