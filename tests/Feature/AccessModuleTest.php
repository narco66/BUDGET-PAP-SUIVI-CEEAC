<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\UserService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['mfa.enabled' => false]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_admin_cree_un_utilisateur_force_changement_mdp(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->post(route('utilisateurs.store'), [
                'name' => 'Agent X', 'email' => 'agentx@ceeac.test', 'statut' => 'actif',
                'roles' => ['gestionnaire_budgetaire'],
            ])->assertRedirect();

        $u = User::firstWhere('email', 'agentx@ceeac.test');
        $this->assertNotNull($u);
        $this->assertTrue($u->doit_changer_mot_de_passe);
        $this->assertTrue($u->hasRole('gestionnaire_budgetaire'));
        $this->assertDatabaseHas('password_histories', ['user_id' => $u->id]);
        $this->assertDatabaseHas('journaux_audit', ['action' => 'utilisateur_cree', 'auditable_id' => $u->id]);
    }

    public function test_attribution_permission_a_un_role(): void
    {
        $this->actingAs($this->user('administrateur'));
        $role = Role::create(['name' => 'role_test', 'guard_name' => 'web']);

        $this->put(route('roles.update', $role), [
            'name' => 'role_test', 'libelle' => 'Rôle Test', 'type' => 'metier',
            'permissions' => ['pap.voir', 'pap.creer'],
        ])->assertRedirect();

        $this->assertTrue($role->fresh()->hasPermissionTo('pap.creer'));
    }

    public function test_restriction_acces_module_non_autorise(): void
    {
        // point_focal n'a pas utilisateur.voir
        $this->actingAs($this->user('point_focal'))
            ->get(route('utilisateurs.index'))->assertForbidden();
    }

    public function test_non_admin_ne_peut_pas_creer_un_utilisateur(): void
    {
        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->get(route('utilisateurs.create'))->assertForbidden();
    }

    public function test_garde_anti_attribution_du_role_administrateur(): void
    {
        $service = app(UserService::class);
        $auteur = $this->user('directeur');          // non administrateur
        $cible = $this->user('point_focal');

        $service->synchroniserRoles($cible, ['administrateur', 'point_focal'], $auteur);

        $this->assertFalse($cible->fresh()->hasRole('administrateur')); // refusé
        $this->assertTrue($cible->fresh()->hasRole('point_focal'));
    }

    public function test_anti_auto_escalade(): void
    {
        $service = app(UserService::class);
        $self = $this->user('directeur');

        $service->synchroniserRoles($self, ['administrateur', 'controleur_financier'], $self);

        $this->assertFalse($self->fresh()->hasRole('administrateur'));
        $this->assertFalse($self->fresh()->hasRole('controleur_financier')); // rôle protégé non auto-attribuable
    }

    public function test_role_protege_non_supprimable(): void
    {
        $this->actingAs($this->user('administrateur'));
        $admin = Role::findByName('administrateur');

        $this->delete(route('roles.destroy', $admin))->assertRedirect();
        $this->assertNotNull(Role::find($admin->id)); // toujours présent
    }

    public function test_role_utilise_non_supprimable(): void
    {
        $this->actingAs($this->user('administrateur'));
        $role = Role::create(['name' => 'role_libre', 'guard_name' => 'web']);
        $this->user('point_focal')->assignRole($role); // rôle désormais utilisé

        $this->delete(route('roles.destroy', $role))->assertRedirect()->assertSessionHas('erreur');
        $this->assertNotNull(Role::find($role->id));
    }

    public function test_acces_par_structure(): void
    {
        $dep = Structure::create(['code' => 'DEP', 'libelle' => 'Département', 'type' => 'departement']);
        $dir = Structure::create(['code' => 'DIR', 'libelle' => 'Direction', 'type' => 'direction', 'parent_id' => $dep->id]);
        $autre = Structure::create(['code' => 'AUT', 'libelle' => 'Autre', 'type' => 'departement']);

        $u = $this->user('chef_service');
        $u->accessScopes()->create(['type' => 'departement', 'scope_id' => $dep->id]);

        $ids = app(AccessScopeService::class)->structuresAutorisees($u);
        $this->assertTrue($ids->contains($dep->id));
        $this->assertTrue($ids->contains($dir->id));   // descendante incluse
        $this->assertFalse($ids->contains($autre->id)); // hors périmètre
    }

    public function test_mot_de_passe_reutilise_detecte(): void
    {
        $service = app(UserService::class);
        $u = $this->user('point_focal');
        $service->definirMotDePasse($u, 'Secret-2026');

        $this->assertTrue($service->motDePasseReutilise($u, 'Secret-2026'));
        $this->assertFalse($service->motDePasseReutilise($u, 'Autre-9999'));
    }

    public function test_ecrans_du_module_se_rendent(): void
    {
        $this->actingAs($this->user('administrateur'));
        $cible = $this->user('point_focal');
        $role = \Spatie\Permission\Models\Role::findByName('gestionnaire_budgetaire');

        $this->get(route('utilisateurs.create'))->assertOk()->assertSee('Rôles');
        $this->get(route('utilisateurs.edit', $cible))->assertOk();
        $this->get(route('utilisateurs.scopes.edit', $cible))->assertOk()->assertSee('périmètre', false);
        $this->get(route('roles.create'))->assertOk()->assertSee('Matrice');
        $this->get(route('roles.edit', $role))->assertOk();
        $this->get(route('permissions.index'))->assertOk();
        $this->get(route('profils.index'))->assertOk();
        $this->get(route('profils.create'))->assertOk();
        $this->get(route('logs.connexions'))->assertOk();
    }

    public function test_menu_filtre_selon_permissions(): void
    {
        // point_focal ne voit pas la gestion des utilisateurs
        $this->actingAs($this->user('point_focal'))
            ->get(route('dashboard'))->assertOk()->assertDontSee('Rôles & permissions');
    }
}
