<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use App\Support\PersonaTableauxBord;
use Database\Seeders\ReportingPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 — Profilage des tableaux de bord par persona : selon le rôle institutionnel, l'utilisateur se
 * voit proposer les cockpits pertinents (exécutif / stratégique / opérationnel / financier), à
 * partir du RBAC existant et sans nouveau dashboard.
 */
class PersonaTableauxBordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
        $this->seed(ReportingPermissionsSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_persona_executif_pour_le_president(): void
    {
        $profil = PersonaTableauxBord::pour($this->user('president'));

        $this->assertSame('Exécutif', $profil['persona']);
        $routes = array_column($profil['cartes'], 'route');
        $this->assertContains('reporting.cockpit', $routes);
        $this->assertContains('tableaux.index', $routes);
    }

    public function test_persona_financier_pour_le_comptable(): void
    {
        $profil = PersonaTableauxBord::pour($this->user('comptable'));

        $this->assertSame('Financier', $profil['persona']);
        $this->assertContains('suivi-financier.index', array_column($profil['cartes'], 'route'));
    }

    public function test_persona_operationnel_pour_le_chef_service(): void
    {
        $profil = PersonaTableauxBord::pour($this->user('chef_service'));

        $this->assertSame('Opérationnel', $profil['persona']);
        $this->assertContains('paps.dashboard', array_column($profil['cartes'], 'route'));
    }

    public function test_profil_consultation_par_defaut_sans_role_connu(): void
    {
        $profil = PersonaTableauxBord::pour(User::factory()->create(['actif' => true]));

        $this->assertSame('Consultation', $profil['persona']);
        $this->assertSame('consultation', $profil['persona_key']);
        $this->assertContains('budget_kpi', $profil['widgets']);
        $this->assertNotContains('charts_engagements', $profil['widgets']);
    }

    public function test_widgets_executif_et_lecteur(): void
    {
        $exec = PersonaTableauxBord::widgets($this->user('secretaire_general'));
        $lecteur = PersonaTableauxBord::widgets($this->user('lecteur'));

        $this->assertContains('budget_chain', $exec);
        $this->assertContains('activity_feed', $exec);
        $this->assertNotContains('budget_chain', $lecteur);
    }

    public function test_persona_gestionnaire_budgetaire_inclut_tresorerie(): void
    {
        $profil = PersonaTableauxBord::pour($this->user('gestionnaire_budgetaire'));

        $this->assertSame('Budgétaire', $profil['persona']);
        $this->assertContains('tresorerie', array_column($profil['cartes'], 'cle'));
        $this->assertContains('recettes', $profil['tableaux_widgets']);
    }

    public function test_cle_persona_sg_est_executif(): void
    {
        $this->assertSame('executif', PersonaTableauxBord::clePersona($this->user('secretaire_general')));
    }

    public function test_dashboard_affiche_la_bande_persona(): void
    {
        Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        $this->actingAs($this->user('administrateur'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mes tableaux de bord')
            ->assertSee('Cockpit exécutif');
    }
}
