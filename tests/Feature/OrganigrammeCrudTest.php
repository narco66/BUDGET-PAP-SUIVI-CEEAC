<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganigrammeCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::create([
            'name' => 'Admin Org',
            'email' => 'admin.org@ceeac.test',
            'password' => bcrypt('x'),
            'actif' => true,
            'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_organigramme_affiche_actions_crud(): void
    {
        Structure::updateOrCreate(
            ['code' => 'PRES'],
            ['libelle' => 'Presidence', 'type' => 'presidence', 'niveau' => 0]
        );

        $this->actingAs($this->admin)->get(route('organigramme'))
            ->assertOk()
            ->assertSee('Nouvelle racine')
            ->assertSee('Presidence');
    }

    public function test_ajout_sous_structure_depuis_organigramme(): void
    {
        $parent = Structure::updateOrCreate(
            ['code' => 'SG'],
            ['libelle' => 'Secretariat General', 'type' => 'secretariat_general', 'niveau' => 0]
        );

        $this->actingAs($this->admin)->get(route('structures.create', ['parent_id' => $parent->id, 'retour' => 'organigramme']))
            ->assertOk();

        $this->actingAs($this->admin)->post(route('structures.store'), [
            'code' => 'DIR-RH',
            'libelle' => 'Direction RH',
            'type' => 'direction',
            'parent_id' => $parent->id,
            'ordre' => 0,
            'actif' => 1,
            'retour' => 'organigramme',
        ])->assertRedirect(route('organigramme'));

        $this->assertDatabaseHas('structures', ['code' => 'DIR-RH', 'parent_id' => $parent->id, 'niveau' => 1]);
    }

    public function test_suppression_depuis_organigramme(): void
    {
        $s = Structure::create(['code' => 'TMP', 'libelle' => 'Temporaire', 'type' => 'service', 'niveau' => 0]);

        $this->actingAs($this->admin)->delete(route('structures.destroy', $s), ['retour' => 'organigramme'])
            ->assertRedirect(route('organigramme'));

        $this->assertSoftDeleted('structures', ['id' => $s->id]);
    }
}
