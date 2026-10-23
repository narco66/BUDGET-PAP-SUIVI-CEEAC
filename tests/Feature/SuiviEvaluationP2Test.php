<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\MissionSuivi;
use App\Models\PlanAmelioration;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviEvaluationPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P2 — missions, plans d'amélioration et actions correctives. */
class SuiviEvaluationP2Test extends TestCase
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

    public function test_crud_mission_suivi(): void
    {
        $this->actingAs($this->admin())
            ->post(route('suivi-evaluation.missions.store'), [
                'exercice_id' => $this->exercice->id,
                'type' => 'suivi',
                'libelle' => 'Mission test P2',
                'statut' => 'planifiee',
            ])
            ->assertRedirect();

        $mission = MissionSuivi::firstWhere('libelle', 'Mission test P2');
        $this->actingAs($this->admin())
            ->get(route('suivi-evaluation.missions.show', $mission))
            ->assertOk()
            ->assertSee('Mission test P2');
    }

    public function test_plan_avec_action_recalcule_taux(): void
    {
        $this->actingAs($this->admin())
            ->post(route('suivi-evaluation.plans.store'), [
                'exercice_id' => $this->exercice->id,
                'intitule' => 'Plan test P2',
                'statut' => 'en_cours',
            ])
            ->assertRedirect();

        $plan = PlanAmelioration::firstWhere('intitule', 'Plan test P2');

        $this->actingAs($this->admin())
            ->post(route('suivi-evaluation.plans.actions.store', $plan), [
                'libelle' => 'Action A',
                'statut' => 'en_cours',
                'taux' => 80,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->post(route('suivi-evaluation.plans.actions.store', $plan), [
                'libelle' => 'Action B',
                'statut' => 'en_attente',
                'taux' => 40,
            ]);

        $this->assertEquals(60.0, (float) $plan->fresh()->taux_realisation);
    }

    public function test_hub_affiche_missions_et_plans(): void
    {
        MissionSuivi::create([
            'numero' => 'MS-P2-001', 'exercice_id' => $this->exercice->id,
            'type' => 'audit', 'libelle' => 'Audit P2', 'statut' => 'en_cours',
        ]);

        $this->actingAs($this->admin())
            ->get(route('suivi-evaluation.index', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('1 en cours')
            ->assertSee('Plans d');
    }
}
