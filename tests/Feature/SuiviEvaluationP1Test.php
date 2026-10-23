<?php

namespace Tests\Feature;

use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Suivi\SuiviEvaluationService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviEvaluationPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P1 — rapports, capture batch et détail évaluations. */
class SuiviEvaluationP1Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SuiviEvaluationPermissionsSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_commande_capture_exercice(): void
    {
        $this->artisan('suivi-evaluation:capture', ['--exercice' => $this->exercice->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('evaluations_periodiques', [
            'exercice_id' => $this->exercice->id,
            'type' => 'annuelle',
        ]);
    }

    public function test_rapport_suivi_evaluation(): void
    {
        app(SuiviEvaluationService::class)->genererEvaluation($this->exercice->id, null, $this->admin()->id);

        $this->actingAs($this->admin())
            ->get(route('reporting.show', ['type' => 'suivi_evaluation', 'exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Score global S&E');
    }

    public function test_detail_evaluation_periodique(): void
    {
        $eval = app(SuiviEvaluationService::class)->genererEvaluation($this->exercice->id, null, $this->admin()->id);

        $this->actingAs($this->admin())
            ->get(route('suivi-evaluation.evaluations.show', $eval))
            ->assertOk()
            ->assertSee($eval->periode);
    }

    public function test_historique_exercice(): void
    {
        $svc = app(SuiviEvaluationService::class);
        $svc->genererEvaluation($this->exercice->id, null, $this->admin()->id);

        $this->assertCount(1, $svc->historiqueExercice($this->exercice->id));
        $this->assertNotEmpty($svc->evolutionScores($this->exercice->id));
    }
}
