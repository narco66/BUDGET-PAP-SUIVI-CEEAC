<?php

namespace Tests\Feature;

use App\Models\HistoriqueAffectation;
use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\InstitutionGouvernanceService;
use App\Services\UserService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase A6 — délégations, intérims et historiques d'affectation. */
class ReferentielAdministratifPhaseA6Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('administrateur');
    }

    public function test_tables_gouvernance_existent(): void
    {
        $this->assertTrue(\Schema::hasTable('delegations'));
        $this->assertTrue(\Schema::hasTable('interims'));
        $this->assertTrue(\Schema::hasTable('historiques_affectations'));
    }

    public function test_changement_structure_historise(): void
    {
        $admin = $this->admin();
        $s1 = Structure::create(['code' => 'S1', 'libelle' => 'Structure 1', 'type' => 'departement', 'actif' => true]);
        $s2 = Structure::create(['code' => 'S2', 'libelle' => 'Structure 2', 'type' => 'departement', 'actif' => true]);

        $user = User::factory()->create([
            'structure_id' => $s1->id,
            'actif' => true,
            'statut' => 'actif',
        ]);

        app(UserService::class)->mettreAJour($user, [
            'name' => $user->name,
            'email' => $user->email,
            'structure_id' => $s2->id,
        ], $admin);

        $this->assertDatabaseHas('historiques_affectations', [
            'user_id' => $user->id,
            'structure_id' => $s1->id,
        ]);
        $this->assertDatabaseHas('historiques_affectations', [
            'user_id' => $user->id,
            'structure_id' => $s2->id,
        ]);

        $this->assertSame(1, HistoriqueAffectation::where('user_id', $user->id)->whereNull('date_fin')->count());
    }

    public function test_delegation_active_enregistree(): void
    {
        $structure = Structure::create(['code' => 'DEP-DEL', 'libelle' => 'Dept', 'type' => 'departement', 'actif' => true]);
        $deleguant = User::factory()->create(['actif' => true]);
        $delegue = User::factory()->create(['actif' => true]);

        $delegation = app(InstitutionGouvernanceService::class)->creerDelegation([
            'structure_id' => $structure->id,
            'delegue_par' => $deleguant->id,
            'delegue_a' => $delegue->id,
            'date_debut' => now()->toDateString(),
            'motif' => 'Absence mission',
        ]);

        $this->assertTrue(app(InstitutionGouvernanceService::class)->utilisateurEstDelegueSurStructure($delegue, $structure->id));
        $this->assertTrue($delegation->actif);
    }

    public function test_interim_actif_enregistre(): void
    {
        $structure = Structure::create(['code' => 'DEP-INT', 'libelle' => 'Dept int', 'type' => 'departement', 'actif' => true]);
        $titulaire = User::factory()->create(['actif' => true]);
        $interim = User::factory()->create(['actif' => true]);
        $admin = $this->admin();

        app(InstitutionGouvernanceService::class)->creerInterim([
            'structure_id' => $structure->id,
            'titulaire_user_id' => $titulaire->id,
            'interim_user_id' => $interim->id,
            'date_debut' => now()->toDateString(),
            'cree_par' => $admin->id,
        ]);

        $this->assertTrue(app(InstitutionGouvernanceService::class)->utilisateurEstInterimSurStructure($interim, $structure->id));
    }

    public function test_delegation_invalide_refusee(): void
    {
        $structure = Structure::create(['code' => 'DEP-INV', 'libelle' => 'Dept inv', 'type' => 'departement', 'actif' => true]);
        $user = User::factory()->create(['actif' => true]);

        $this->expectException(\InvalidArgumentException::class);

        app(InstitutionGouvernanceService::class)->creerDelegation([
            'structure_id' => $structure->id,
            'delegue_par' => $user->id,
            'delegue_a' => $user->id,
            'date_debut' => now()->toDateString(),
        ]);
    }

    public function test_audit_signale_gouvernance_invalide(): void
    {
        $structure = Structure::create(['code' => 'DEP-AUD', 'libelle' => 'Dept aud', 'type' => 'departement', 'actif' => true]);
        $user = User::factory()->create(['actif' => true]);

        \App\Models\Delegation::create([
            'structure_id' => $structure->id,
            'delegue_par' => $user->id,
            'delegue_a' => $user->id,
            'date_debut' => now()->toDateString(),
            'actif' => true,
        ]);

        $anomalies = app(InstitutionGouvernanceService::class)->listerAnomalies();

        $this->assertNotEmpty($anomalies);
        $this->artisan('institution:audit-cadre')->assertExitCode(1);
    }
}
