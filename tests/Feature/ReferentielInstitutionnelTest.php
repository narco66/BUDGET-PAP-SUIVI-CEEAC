<?php

namespace Tests\Feature;

use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferentielInstitutionnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function utilisateur(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_invite_est_redirige_vers_login(): void
    {
        $this->get(route('structures.index'))->assertRedirect(route('login'));
    }

    public function test_admin_accede_au_referentiel(): void
    {
        $this->actingAs($this->utilisateur('administrateur'))
            ->get(route('structures.index'))
            ->assertOk();
    }

    public function test_admin_cree_une_structure_et_action_journalisee(): void
    {
        $admin = $this->utilisateur('administrateur');
        $presidence = Structure::create([
            'code' => 'PRES-TEST',
            'libelle' => 'Présidence test',
            'type' => 'presidence',
            'niveau' => 0,
            'actif' => true,
        ]);

        $this->actingAs($admin)->post(route('structures.store'), [
            'code' => 'DEPT-TEST',
            'libelle' => 'Département de test',
            'type' => 'departement',
            'parent_id' => $presidence->id,
            'actif' => 1,
        ])->assertRedirect(route('structures.index'));

        $structure = Structure::firstWhere('code', 'DEPT-TEST');
        $this->assertNotNull($structure);

        $this->assertDatabaseHas('journaux_audit', [
            'action' => 'created',
            'auditable_type' => Structure::class,
            'auditable_id' => $structure->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_profil_sans_droit_de_creation_est_refuse(): void
    {
        // contrôleur financier : pas de permission structure.creer
        $this->actingAs($this->utilisateur('controleur_financier'))
            ->get(route('structures.create'))
            ->assertForbidden();
    }

    public function test_point_focal_ne_voit_pas_les_utilisateurs(): void
    {
        $this->actingAs($this->utilisateur('point_focal'))
            ->get(route('utilisateurs.index'))
            ->assertForbidden();
    }
}
