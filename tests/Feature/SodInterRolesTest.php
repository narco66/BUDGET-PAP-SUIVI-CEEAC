<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\JournalAudit;
use App\Models\Pap;
use App\Models\User;
use App\Services\UserService;
use App\Services\WorkflowService;
use Database\Seeders\MotifsRejetSeeder;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/** P2-3 — SoD inter-rôles et initiateur ≠ validateur. */
class SodInterRolesTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            RecettesPermissionsSeeder::class,
            WorkflowPermissionsSeeder::class,
            MotifsRejetSeeder::class,
            WorkflowSeeder::class,
        ]);
        $this->workflow = app(WorkflowService::class);
        config(['mfa.enabled' => false]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_createur_ne_peut_pas_valider_son_propre_dossier(): void
    {
        config([
            'workflow.sod.enabled' => true,
            'workflow.sod.creator_cannot_validate' => true,
        ]);

        $chef = $this->user('chef_service');
        $pap = $this->pap($chef);

        $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('initiateur');
        $this->workflow->valider($pap->workflowInstances()->first()->fresh(), $chef);
    }

    public function test_createur_peut_valider_si_sod_desactivee(): void
    {
        config([
            'workflow.sod.enabled' => true,
            'workflow.sod.creator_cannot_validate' => false,
        ]);

        $chef = $this->user('chef_service');
        $pap = $this->pap($chef);
        $instance = $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->workflow->valider($instance->fresh(), $chef);

        $this->assertSame('valide', $instance->fresh()->etapes()->where('ordre', 1)->first()->statut);
    }

    public function test_roles_incompatibles_refuses_a_la_synchronisation(): void
    {
        config(['workflow.sod.incompatible_roles_enabled' => true]);

        $service = app(UserService::class);
        $cible = $this->user('point_focal');
        $auteur = $this->user('administrateur');

        $this->expectException(ValidationException::class);
        $service->synchroniserRoles($cible, ['gestionnaire_budgetaire', 'controleur_financier'], $auteur);
    }

    public function test_roles_incompatibles_journalises_en_formulaire_utilisateur(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->from(route('utilisateurs.create'))
            ->post(route('utilisateurs.store'), [
                'name' => 'Agent SoD',
                'email' => 'sod@ceeac.test',
                'statut' => 'actif',
                'roles' => ['gestionnaire_budgetaire', 'controleur_financier'],
            ])
            ->assertRedirect(route('utilisateurs.create'))
            ->assertSessionHasErrors('roles');
    }

    public function test_violation_sod_createur_journalisee(): void
    {
        config(['workflow.sod.creator_cannot_validate' => true]);

        $chef = $this->user('chef_service');
        $pap = $this->pap($chef);
        $instance = $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));

        try {
            $this->workflow->valider($instance->fresh(), $chef);
        } catch (RuntimeException) {
            // attendu
        }

        $this->assertDatabaseHas('journaux_audit', ['action' => 'sod_violation']);
        $violation = JournalAudit::where('action', 'sod_violation')->latest('id')->first();
        $this->assertStringContainsString('créateur', $violation->apres['motif'] ?? '');
    }

    private function pap(User $createur): Pap
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        return Pap::create([
            'exercice_id' => $exercice->id,
            'reference' => 'PAP-SOD',
            'libelle' => 'PAP test SoD',
            'statut' => 'brouillon',
            'cree_par' => $createur->id,
        ]);
    }
}
