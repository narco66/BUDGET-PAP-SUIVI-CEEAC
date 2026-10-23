<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Pap;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCondition;
use App\Models\WorkflowEtape;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowConditionEvaluator;
use App\Services\Workflow\WorkflowTransitionResolver;
use App\Services\Workflow\WorkflowVersionService;
use App\Services\WorkflowService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase W-P3 — versionning, parallèle, conditionnel, API REST. */
class WorkflowP3Test extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, WorkflowPermissionsSeeder::class, WorkflowSeeder::class]);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_tables_p3_existent(): void
    {
        foreach (['workflow_versions', 'workflow_conditions', 'workflow_transitions'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }

    public function test_publication_version_circuit(): void
    {
        $admin = $this->user('administrateur');
        $papWorkflow = Workflow::where('code', 'PAP')->firstOrFail();

        $version = app(WorkflowVersionService::class)->publier($papWorkflow, $admin, 'PAP v2');

        $this->assertSame(2, (int) $papWorkflow->fresh()->version);
        $this->assertDatabaseHas('workflow_versions', ['workflow_id' => $papWorkflow->id, 'numero' => 2]);
        $this->assertNotEmpty($version->snapshot_json['etapes']);
    }

    public function test_validation_parallele_deux_roles_meme_ordre(): void
    {
        $wf = Workflow::create([
            'code' => 'TEST_PAR',
            'libelle' => 'Test parallèle',
            'type_objet' => 'pap',
            'actif' => true,
        ]);
        foreach ([['chef_service', 1], ['controleur_financier', 2], ['secretaire_general', 3]] as [$role, $ordre]) {
            WorkflowEtape::create([
                'workflow_id' => $wf->id,
                'ordre' => $ordre,
                'libelle' => "Étape {$role}",
                'role' => $role,
                'type_approbation' => in_array($ordre, [1, 2], true) ? 'parallele' : 'sequentiel',
                'groupe_parallele' => in_array($ordre, [1, 2], true) ? 'G1' : null,
            ]);
        }

        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2028, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-PAR',
            'libelle' => 'PAP parallèle',
            'statut' => 'brouillon',
        ]);

        $instance = $this->workflow->demarrer($pap, 'TEST_PAR', $this->user('directeur'));
        $this->assertSame(1, $instance->etape_courante);

        $this->workflow->valider($instance->fresh(), $this->user('chef_service'));
        $this->assertSame(1, $instance->fresh()->etape_courante);

        $cf = $this->user('controleur_financier');
        $this->actingAs($cf);
        $this->workflow->valider($instance->fresh(), $cf);

        $this->assertSame(3, $instance->fresh()->etape_courante);

        $sg = $this->user('secretaire_general');
        $this->actingAs($sg);
        $this->workflow->valider($instance->fresh(), $sg);

        $this->assertSame('valide', $instance->fresh()->statut);
    }

    public function test_transition_conditionnelle_saute_etape(): void
    {
        $wf = Workflow::create([
            'code' => 'TEST_COND',
            'libelle' => 'Test conditionnel',
            'type_objet' => 'pap',
            'actif' => true,
        ]);
        foreach ([1 => 'directeur', 2 => 'chef_service', 3 => 'secretaire_general'] as $ordre => $role) {
            WorkflowEtape::create([
                'workflow_id' => $wf->id,
                'ordre' => $ordre,
                'libelle' => "Étape {$ordre}",
                'role' => $role,
            ]);
        }

        $condition = WorkflowCondition::create([
            'workflow_id' => $wf->id,
            'code' => 'VIP',
            'libelle' => 'Référence VIP',
            'expression' => ['champ' => 'reference', 'operateur' => '==', 'valeur' => 'PAP-VIP'],
        ]);

        WorkflowTransition::create([
            'workflow_id' => $wf->id,
            'etape_source_ordre' => 1,
            'etape_cible_ordre' => 3,
            'condition_id' => $condition->id,
            'type' => 'conditionnel',
            'priorite' => 10,
        ]);

        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2029, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-VIP',
            'libelle' => 'PAP VIP',
            'statut' => 'brouillon',
        ]);

        $instance = $this->workflow->demarrer($pap, 'TEST_COND', $this->user('comptable'));
        $this->workflow->valider($instance->fresh(), $this->user('directeur'));

        $this->assertSame(3, $instance->fresh()->etape_courante);
    }

    public function test_evaluateur_condition_montant(): void
    {
        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2030, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'REF',
            'libelle' => 'Test',
            'statut' => 'brouillon',
        ]);

        $condition = new WorkflowCondition([
            'expression' => ['champ' => 'reference', 'operateur' => '==', 'valeur' => 'REF'],
            'actif' => true,
        ]);

        $this->assertTrue(app(WorkflowConditionEvaluator::class)->satisfait($condition, $pap));
    }

    public function test_api_circuits_et_corbeille(): void
    {
        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2031, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-API',
            'libelle' => 'PAP API',
            'statut' => 'brouillon',
        ]);
        $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));

        $cs = $this->user('chef_service');
        Sanctum::actingAs($cs, ['lecture']);

        $this->getJson(route('api.workflow.circuits'))
            ->assertOk()
            ->assertJsonFragment(['code' => 'PAP']);

        $this->getJson(route('api.workflow.instances'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_api_decision_valider(): void
    {
        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2032, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-DEC',
            'libelle' => 'PAP décision API',
            'statut' => 'brouillon',
        ]);
        $instance = $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));

        $cs = $this->user('chef_service');
        Sanctum::actingAs($cs, ['lecture']);

        $this->postJson(route('api.workflow.decision', $instance), [
            'decision' => 'valider',
            'observation' => 'OK via API',
        ])->assertOk()
            ->assertJsonPath('data.statut', 'en_cours');
    }

    public function test_instance_liee_a_version_publiee(): void
    {
        $admin = $this->user('administrateur');
        $wf = Workflow::where('code', 'PAP')->firstOrFail();
        $version = app(WorkflowVersionService::class)->publier($wf, $admin);

        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2033, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-VER',
            'libelle' => 'PAP version',
            'statut' => 'brouillon',
        ]);

        $instance = $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->assertSame($version->id, $instance->workflow_version_id);
    }
}
