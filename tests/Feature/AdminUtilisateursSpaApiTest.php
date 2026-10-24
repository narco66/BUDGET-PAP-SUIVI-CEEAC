<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUtilisateursSpaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_hub_crud_statut_et_reinitialisation(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->getJson(route('api.utilisateurs.hub'))
            ->assertForbidden();

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.utilisateurs.hub'))
            ->assertOk()
            ->assertJsonPath('actions.creer', false);

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.utilisateurs.store'), [
                'name' => 'Agent SPA',
                'email' => 'agent.spa@ceeac.test',
                'statut' => 'actif',
                'roles' => ['gestionnaire_budgetaire'],
            ])
            ->assertForbidden();

        $admin = $this->user('administrateur');
        $creation = $this->actingAs($admin)
            ->postJson(route('api.utilisateurs.store'), [
                'name' => 'Agent SPA',
                'email' => 'agent.spa@ceeac.test',
                'statut' => 'actif',
                'roles' => ['gestionnaire_budgetaire'],
            ]);
        $creation->assertCreated()->assertJsonPath('utilisateur.doit_changer_mot_de_passe', true);
        $id = $creation->json('utilisateur.id');

        $this->actingAs($admin)
            ->putJson(route('api.utilisateurs.update', $id), [
                'name' => 'Agent SPA modifié',
                'email' => 'agent.spa@ceeac.test',
                'statut' => 'actif',
                'roles' => ['gestionnaire_budgetaire'],
            ])
            ->assertOk()
            ->assertJsonPath('utilisateur.name', 'Agent SPA modifié');

        $this->actingAs($admin)
            ->postJson(route('api.utilisateurs.statut', $id), ['statut' => 'suspendu'])
            ->assertOk()
            ->assertJsonPath('utilisateur.statut', 'suspendu');

        $reset = $this->actingAs($admin)
            ->postJson(route('api.utilisateurs.reinitialiser', $id));
        $reset->assertOk()->assertJsonStructure(['mot_de_passe_temporaire']);

        $this->actingAs($admin)
            ->putJson(route('api.utilisateurs.perimetres.update', $id), [
                'scopes' => [['type' => 'lecture_seule']],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('api.utilisateurs.statut', $admin->id), ['statut' => 'suspendu'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');
    }
}
