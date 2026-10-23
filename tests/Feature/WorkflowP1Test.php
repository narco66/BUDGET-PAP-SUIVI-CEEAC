<?php

namespace Tests\Feature;

use App\Models\AuditWorkflow;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEtape;
use App\Services\Workflow\WorkflowCorbeilleService;
use App\Services\Workflow\WorkflowRelanceService;
use App\Services\WorkflowService;
use Database\Seeders\NotificationsAlertesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Phase W-P1 — admin, corbeille, audit, relances. */
class WorkflowP1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, NotificationsAlertesPermissionsSeeder::class, WorkflowPermissionsSeeder::class, WorkflowSeeder::class]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_admin_index_reserve_aux_administrateurs(): void
    {
        $admin = $this->user('administrateur');
        $this->actingAs($admin)->get(route('admin.workflows.index'))->assertOk();

        $this->actingAs($this->user('comptable'))->get(route('admin.workflows.index'))->assertForbidden();
    }

    public function test_admin_peut_creer_un_circuit(): void
    {
        $admin = $this->user('administrateur');

        $this->actingAs($admin)->post(route('admin.workflows.store'), [
            'code' => 'TEST-P1',
            'libelle' => 'Circuit test P1',
            'type_objet' => 'test',
            'actif' => 1,
            'etapes' => [
                ['ordre' => 1, 'libelle' => 'Étape 1', 'role' => 'directeur', 'delai_jours' => 3],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('workflows', ['code' => 'TEST-P1']);
        $this->assertSame(1, Workflow::where('code', 'TEST-P1')->first()?->etapes()->count());
    }

    public function test_corbeille_liste_les_taches_du_role(): void
    {
        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-CORB',
            'libelle' => 'PAP corbeille',
            'statut' => 'brouillon',
        ]);

        app(WorkflowService::class)->demarrer($pap, 'PAP', $this->user('directeur'));

        $chef = $this->user('chef_service');
        $taches = app(WorkflowCorbeilleService::class)->tachesPour($chef);

        $this->assertCount(1, $taches);
        $this->assertSame('PAP-CORB', $taches->first()['libelle']);
    }

    public function test_page_corbeille_http(): void
    {
        $this->actingAs($this->user('chef_service'))
            ->get(route('workflow.validations'))
            ->assertOk();
    }

    public function test_audit_workflow_enregistre_demarrage(): void
    {
        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-AUD',
            'libelle' => 'PAP audit',
            'statut' => 'brouillon',
        ]);

        app(WorkflowService::class)->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->assertDatabaseHas('audit_workflow', [
            'workflow_code' => 'PAP',
            'action' => 'workflow_demarre',
        ]);
    }

    public function test_page_audit_workflow(): void
    {
        AuditWorkflow::create([
            'workflow_code' => 'PAP',
            'action' => 'workflow_demarre',
            'created_at' => now(),
        ]);

        $auditeur = $this->user('auditeur');
        $this->actingAs($auditeur)->get(route('workflow.audit'))->assertOk();
    }

    public function test_commande_relances_notifie_et_journalise(): void
    {
        Notification::fake();

        $wf = Workflow::where('code', 'ENGAGEMENT')->firstOrFail();
        $wf->etapes()->update(['delai_jours' => 1]);

        $cf = $this->user('controleur_financier');

        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $engagement = \App\Models\Engagement::create([
            'exercice_id' => $exercice->id,
            'numero' => 'ENG-REL',
            'objet' => 'Test relance',
            'type' => 'simple',
            'montant_total' => 1000,
            'statut' => 'brouillon',
            'createur_id' => $this->user('gestionnaire_budgetaire')->id,
        ]);

        $instance = app(WorkflowService::class)->demarrer($engagement, 'ENGAGEMENT', $this->user('gestionnaire_budgetaire'));
        $etape = $instance->fresh()->etapeCourante();
        $etape->update(['echeance_le' => now()->subDay()]);

        $count = app(WorkflowRelanceService::class)->traiter();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('audit_workflow', ['action' => 'workflow_relance', 'workflow_code' => 'ENGAGEMENT']);
        Notification::assertSentTo($cf, \App\Notifications\InstitutionInAppNotification::class);
    }
}
