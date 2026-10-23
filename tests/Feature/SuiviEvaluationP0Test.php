<?php

namespace Tests\Feature;

use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Models\Recommandation;
use App\Models\User;
use App\Services\Suivi\SuiviEvaluationCalculator;
use App\Services\Suivi\SuiviEvaluationService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviEvaluationPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P0 — scores S&E unifiés, permissions et évaluations périodiques. */
class SuiviEvaluationP0Test extends TestCase
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

    public function test_calculator_score_global_pondere(): void
    {
        $s = SuiviEvaluationCalculator::synthese([
            'physique' => 80,
            'iov' => 70,
            'recommandations' => 60,
            'formations' => 50,
            'conformite' => 90,
        ]);

        $this->assertEquals(80, $s['score_physique']);
        $this->assertEqualsWithDelta(72.0, $s['score_global'], 0.1);
        $this->assertEquals('partiel', SuiviEvaluationCalculator::niveauPerformance($s['score_global']));
    }

    public function test_calculator_score_iov_depuis_feux(): void
    {
        $this->assertEquals(100.0, SuiviEvaluationCalculator::scoreIov(2, 0, 0));
        $this->assertEquals(75.0, SuiviEvaluationCalculator::scoreIov(1, 1, 0));
        $this->assertEquals(0.0, SuiviEvaluationCalculator::scoreIov(0, 0, 0));
    }

    public function test_generer_evaluation_periodique(): void
    {
        Recommandation::create([
            'numero' => 'REC-P0-001', 'exercice_id' => $this->exercice->id,
            'source' => 'autre', 'categorie' => 'gouvernance', 'libelle' => 'Test',
            'priorite' => 'moyenne', 'statut' => 'realisee', 'taux_execution' => 100,
        ]);

        $eval = app(SuiviEvaluationService::class)->genererEvaluation(
            $this->exercice->id,
            null,
            $this->admin()->id,
        );

        $this->assertDatabaseHas('evaluations_periodiques', [
            'exercice_id' => $this->exercice->id,
            'type' => 'annuelle',
        ]);
        $this->assertGreaterThan(0, (float) $eval->score_global);
        $this->assertNotNull($eval->details);
    }

    public function test_recommandation_avec_categorie(): void
    {
        $rec = Recommandation::create([
            'numero' => 'REC-P0-002', 'exercice_id' => $this->exercice->id,
            'source' => 'mission_audit', 'categorie' => 'financier', 'libelle' => 'Audit',
            'priorite' => 'haute', 'statut' => 'en_cours', 'taux_execution' => 40,
        ]);

        $this->assertEquals('financier', $rec->categorie);
    }

    public function test_hub_accessible_avec_permission(): void
    {
        $this->actingAs($this->admin())
            ->get(route('suivi-evaluation.index', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Suivi-Évaluation');
    }

    public function test_hub_refuse_sans_permission(): void
    {
        $user = User::factory()->create(['actif' => true]);

        $this->actingAs($user)
            ->get(route('suivi-evaluation.index'))
            ->assertForbidden();
    }

    public function test_alias_permission_suivi_evaluation_view(): void
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo('suivi.voir');

        $this->actingAs($user)
            ->get(route('suivi-evaluation.index'))
            ->assertOk();
    }

    public function test_generer_evaluation_via_post(): void
    {
        $this->actingAs($this->admin())
            ->post(route('suivi-evaluation.generer'), ['exercice' => $this->exercice->id])
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertEquals(1, EvaluationPeriodique::where('exercice_id', $this->exercice->id)->count());
    }
}
