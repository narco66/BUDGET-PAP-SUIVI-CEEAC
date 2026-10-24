<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Pap;
use App\Models\User;
use App\Services\WorkflowService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSpaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, WorkflowPermissionsSeeder::class, WorkflowSeeder::class]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_sans_droit_est_refuse(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true, 'statut' => 'actif']))
            ->getJson(route('api.workflow.hub'))
            ->assertForbidden();
    }

    public function test_hub_corbeille_et_decision(): void
    {
        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-SPA-WF',
            'libelle' => 'PAP workflow SPA',
            'statut' => 'brouillon',
        ]);
        $instance = app(WorkflowService::class)->demarrer($pap, 'PAP', $this->user('directeur'));
        $chef = $this->user('chef_service');

        $this->actingAs($chef)
            ->getJson(route('api.workflow.hub'))
            ->assertOk()
            ->assertJsonPath('kpi.en_cours', 1)
            ->assertJsonPath('corbeille', 1);

        $this->actingAs($chef)
            ->getJson(route('api.workflow.instances'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $instance->id);

        $this->actingAs($chef)
            ->getJson(route('api.workflow.instance', $instance))
            ->assertOk()
            ->assertJsonPath('data.peut_agir', true);

        $this->actingAs($chef)
            ->postJson(route('api.workflow.decision', $instance), [
                'decision' => 'valider',
                'observation' => 'OK SPA',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_cours');
    }

    public function test_admin_circuits_creation_edition_et_toggle(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $this->actingAs($gestionnaire)
            ->getJson(route('api.workflow.admin.circuits'))
            ->assertForbidden();

        $this->actingAs($gestionnaire)
            ->getJson(route('api.workflow.hub'))
            ->assertOk()
            ->assertJsonPath('peut_admin', false);

        $admin = $this->user('administrateur');
        $this->actingAs($admin)
            ->getJson(route('api.workflow.admin.circuits'))
            ->assertOk()
            ->assertJsonFragment(['code' => 'PAP']);

        $this->actingAs($admin)
            ->postJson(route('api.workflow.admin.circuits.store'), [
                'code' => 'TEST-SPA',
                'libelle' => 'Circuit SPA',
                'type_objet' => 'test',
                'actif' => true,
                'etapes' => [
                    ['ordre' => 1, 'libelle' => 'Visa direction', 'role' => 'directeur', 'delai_jours' => 3],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'TEST-SPA')
            ->assertJsonPath('data.etapes.0.role', 'directeur');

        $circuit = \App\Models\Workflow::query()->where('code', 'TEST-SPA')->firstOrFail();

        $this->actingAs($admin)
            ->putJson(route('api.workflow.admin.circuit.update', $circuit), [
                'code' => 'TEST-SPA',
                'libelle' => 'Circuit SPA modifié',
                'type_objet' => 'test',
                'actif' => true,
                'etapes' => [
                    ['id' => $circuit->etapes()->first()?->id, 'ordre' => 1, 'libelle' => 'Visa direction', 'role' => 'directeur', 'delai_jours' => 5],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.libelle', 'Circuit SPA modifié')
            ->assertJsonPath('data.etapes.0.delai_jours', 5);

        $this->actingAs($admin)
            ->postJson(route('api.workflow.admin.circuit.toggle', $circuit))
            ->assertOk()
            ->assertJsonPath('data.actif', false);

        $this->actingAs($this->user('chef_service'))
            ->getJson(route('api.workflow.circuits'))
            ->assertOk()
            ->assertJsonFragment(['code' => 'PAP'])
            ->assertJsonMissing(['code' => 'TEST-SPA']);
    }

    public function test_audit_interdit_sans_droit_autorise_pour_auditeur(): void
    {
        $lecteurMetier = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $lecteurMetier->givePermissionTo('pap.voir');
        $auditeur = $this->user('auditeur');

        \App\Models\AuditWorkflow::query()->create([
            'workflow_code' => 'PAP',
            'objet_type' => \App\Models\Pap::class,
            'objet_id' => 1,
            'action' => 'demarrer',
            'etape_ordre' => 1,
            'acteur_id' => $auditeur->id,
            'observation' => 'Démarrage SPA',
            'created_at' => now(),
        ]);

        $this->actingAs($lecteurMetier)
            ->getJson(route('api.workflow.audit'))
            ->assertForbidden();

        $this->actingAs($auditeur)
            ->getJson(route('api.workflow.audit'))
            ->assertOk()
            ->assertJsonPath('data.0.action', 'demarrer')
            ->assertJsonPath('data.0.circuit', 'PAP')
            ->assertJsonPath('data.0.objet', 'Pap #1')
            ->assertJsonMissingPath('data.0.ip')
            ->assertJsonMissingPath('data.0.user_agent');
    }
}
