<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\User;
use App\Services\WorkflowService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Circuit BUDGET — adoption enveloppe (P1). */
class WorkflowBudgetTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, WorkflowSeeder::class]);
        $this->service = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function budget(): Budget
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        return Budget::create([
            'exercice_id' => $exercice->id,
            'reference' => 'BUDGET-2026',
            'libelle' => 'Budget 2026',
            'statut' => 'brouillon',
        ]);
    }

    public function test_soumission_demarre_circuit_six_etapes(): void
    {
        $budget = $this->budget();
        $instance = $this->service->demarrer($budget, 'BUDGET', $this->user('directeur'));

        $this->assertSame('en_cours', $instance->statut);
        $this->assertCount(6, $instance->etapes);
        $this->assertSame('en_validation', $budget->fresh()->statut);
    }

    public function test_adoption_complete_apres_six_validations(): void
    {
        $budget = $this->budget();
        $instance = $this->service->demarrer($budget, 'BUDGET', $this->user('directeur'));

        foreach (['chef_service', 'directeur', 'commissaire', 'controleur_financier', 'secretaire_general', 'president'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
            $this->service->valider($instance->fresh(), $user);
        }

        $this->assertSame('valide', $instance->fresh()->statut);
        $this->assertSame('adopte', $budget->fresh()->statut);
        $this->assertNotNull($budget->fresh()->adopte_le);
    }

    public function test_etapes_ont_delai_jours_seed(): void
    {
        $wf = \App\Models\Workflow::where('code', 'BUDGET')->with('etapes')->first();
        $this->assertNotNull($wf);
        $this->assertTrue($wf->etapes->every(fn ($e) => (int) $e->delai_jours > 0));
    }
}
