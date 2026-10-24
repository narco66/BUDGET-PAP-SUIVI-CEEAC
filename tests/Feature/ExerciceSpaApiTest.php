<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use Database\Seeders\ExercicePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciceSpaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, ExercicePermissionsSeeder::class]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_hub_crud_ouvrir_et_cloturer(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.exercices.hub'))
            ->assertOk()
            ->assertJsonPath('actions.creer', false);

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.exercices.store'), [
                'code' => '2034',
                'annee' => 2034,
                'libelle' => 'Exercice SPA 2034',
                'date_debut' => '2034-01-01',
                'date_fin' => '2034-12-31',
                'devise' => 'XAF',
            ])
            ->assertForbidden();

        $admin = $this->user('administrateur');
        $creation = $this->actingAs($admin)
            ->postJson(route('api.exercices.store'), [
                'code' => '2034',
                'annee' => 2034,
                'libelle' => 'Exercice SPA 2034',
                'date_debut' => '2034-01-01',
                'date_fin' => '2034-12-31',
                'devise' => 'XAF',
            ]);
        $creation->assertCreated()->assertJsonPath('exercice.statut', 'brouillon');
        $id = $creation->json('exercice.id');

        $this->actingAs($admin)
            ->putJson(route('api.exercices.update', $id), [
                'code' => '2034',
                'annee' => 2034,
                'libelle' => 'Exercice SPA 2034 modifié',
                'date_debut' => '2034-01-01',
                'date_fin' => '2034-12-31',
                'devise' => 'XAF',
            ])
            ->assertOk()
            ->assertJsonPath('exercice.libelle', 'Exercice SPA 2034 modifié');

        $this->actingAs($admin)
            ->postJson(route('api.exercices.ouvrir', $id))
            ->assertOk()
            ->assertJsonPath('exercice.statut', 'ouvert');

        $this->actingAs($admin)
            ->postJson(route('api.exercices.activer', $id))
            ->assertOk()
            ->assertJsonPath('exercice.est_actif', true);

        $this->actingAs($admin)
            ->postJson(route('api.exercices.cloturer', $id))
            ->assertOk()
            ->assertJsonPath('exercice.statut', 'clos');

        $this->assertNotNull(Exercice::find($id)?->date_cloture);
    }
}
