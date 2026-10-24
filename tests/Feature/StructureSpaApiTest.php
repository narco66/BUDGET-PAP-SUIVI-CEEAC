<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructureSpaApiTest extends TestCase
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

    public function test_hub_crud_et_suppression_avec_enfants(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.structures.hub'))
            ->assertOk()
            ->assertJsonPath('actions.creer', false);

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.structures.store'), [
                'code' => 'PRES-SPA',
                'libelle' => 'Présidence SPA',
                'type' => 'presidence',
                'actif' => true,
            ])
            ->assertForbidden();

        $admin = $this->user('administrateur');
        $creation = $this->actingAs($admin)
            ->postJson(route('api.structures.store'), [
                'code' => 'PRES-SPA',
                'libelle' => 'Présidence SPA',
                'type' => 'presidence',
                'actif' => true,
            ]);
        $creation->assertCreated()->assertJsonPath('structure.code', 'PRES-SPA');
        $presId = $creation->json('structure.id');

        $this->actingAs($admin)
            ->putJson(route('api.structures.update', $presId), [
                'code' => 'PRES-SPA',
                'libelle' => 'Présidence SPA modifiée',
                'type' => 'presidence',
                'actif' => true,
            ])
            ->assertOk()
            ->assertJsonPath('structure.libelle', 'Présidence SPA modifiée');

        $enfant = $this->actingAs($admin)
            ->postJson(route('api.structures.store'), [
                'code' => 'DEPT-SPA',
                'libelle' => 'Département SPA',
                'type' => 'departement',
                'parent_id' => $presId,
                'actif' => true,
            ]);
        $enfant->assertCreated();
        $deptId = $enfant->json('structure.id');

        $this->actingAs($admin)
            ->deleteJson(route('api.structures.destroy', $presId))
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');

        $this->actingAs($admin)
            ->getJson(route('api.structures.show', $deptId))
            ->assertOk()
            ->assertJsonPath('structure.parent.id', $presId);

        $this->actingAs($admin)
            ->deleteJson(route('api.structures.destroy', $deptId))
            ->assertOk();

        $this->assertSoftDeleted('structures', ['id' => $deptId]);

        $this->actingAs($admin)
            ->getJson(route('api.structures'))
            ->assertOk();
    }
}
