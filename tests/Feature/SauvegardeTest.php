<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\RestoreOperation;
use App\Models\User;
use App\Services\AdministrationTechnique\BackupService;
use Database\Seeders\AdministrationTechniqueSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot T3 — sauvegardes & restauration (ISO 22301) : exécution + workflow contrôlé 4 yeux. */
class SauvegardeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $admin2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdministrationTechniqueSeeder::class);

        $this->admin = User::create(['name' => 'DSI 1', 'email' => 'dsi1@ceeac.test', 'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif']);
        $this->admin->assignRole('administrateur');
        $this->admin2 = User::create(['name' => 'DSI 2', 'email' => 'dsi2@ceeac.test', 'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif']);
        $this->admin2->assignRole('administrateur');
    }

    public function test_seeder_cree_la_planification_par_defaut(): void
    {
        $this->assertDatabaseHas('backup_schedules', ['type' => 'base', 'frequence' => 'quotidienne']);
    }

    public function test_execution_de_sauvegarde_produit_un_artefact(): void
    {
        $backup = app(BackupService::class)->executer('base', 'manuel', $this->admin->id);

        $this->assertSame('succes', $backup->statut);
        $this->assertNotNull($backup->chemin);
        $this->assertTrue(is_file($backup->chemin));
        $this->assertGreaterThan(0, (int) $backup->taille_octets);
        $this->assertNotNull($backup->checksum);
        @unlink($backup->chemin);
    }

    public function test_sauvegarde_via_http(): void
    {
        $this->actingAs($this->admin)->post(route('sauvegardes.executer'), ['type' => 'base'])->assertRedirect();
        $this->assertSame(1, Backup::count());
        if ($b = Backup::first()) {
            @unlink((string) $b->chemin);
        }
    }

    public function test_workflow_restauration_quatre_yeux(): void
    {
        // 1) Demande par DSI 1.
        $this->actingAs($this->admin)->post(route('sauvegardes.restaurations.demander'), ['type' => 'base', 'motif' => 'Corruption détectée'])->assertSessionHas('succes');
        $op = RestoreOperation::firstWhere('demande_par', $this->admin->id);
        $this->assertSame('demande', $op->statut);

        // 2) Le demandeur ne peut pas valider (séparation des fonctions).
        $this->actingAs($this->admin)->post(route('sauvegardes.restaurations.valider', $op))->assertSessionHas('erreur');
        $this->assertSame('demande', $op->fresh()->statut);

        // 3) Un autre administrateur valide → exécution autorisée.
        $this->actingAs($this->admin2)->post(route('sauvegardes.restaurations.valider', $op))->assertSessionHas('succes');
        $this->assertSame('validee', $op->fresh()->statut);
        $this->assertSame($this->admin2->id, $op->fresh()->valide_par);

        // 4) Marquage exécutée (acte manuel tracé).
        $this->actingAs($this->admin2)->post(route('sauvegardes.restaurations.executee', $op))->assertSessionHas('succes');
        $this->assertSame('executee', $op->fresh()->statut);
    }

    public function test_permissions_sauvegardes(): void
    {
        // Auditeur : peut consulter (admin_technique.view) mais pas sauvegarder.
        $auditeur = User::create(['name' => 'Aud', 'email' => 'aud.bkp@ceeac.test', 'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif']);
        $auditeur->assignRole('auditeur');
        $this->actingAs($auditeur)->get(route('sauvegardes.index'))->assertOk();
        $this->actingAs($auditeur)->post(route('sauvegardes.executer'), ['type' => 'base'])->assertForbidden();

        // Lecteur : aucun accès.
        $lecteur = User::create(['name' => 'Lec', 'email' => 'lec.bkp@ceeac.test', 'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif']);
        $lecteur->assignRole('lecteur');
        $this->actingAs($lecteur)->get(route('sauvegardes.index'))->assertForbidden();
    }
}
