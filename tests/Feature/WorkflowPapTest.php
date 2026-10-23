<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Pap;
use App\Models\User;
use App\Services\WorkflowService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WorkflowPapTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->service = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function pap(): Pap
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        return Pap::create([
            'exercice_id' => $exercice->id,
            'reference' => 'PAP-2026',
            'libelle' => 'PAP 2026',
            'statut' => 'brouillon',
        ]);
    }

    public function test_soumission_demarre_le_circuit_a_six_etapes(): void
    {
        $pap = $this->pap();
        $instance = $this->service->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->assertSame('en_cours', $instance->statut);
        $this->assertSame(1, $instance->etape_courante);
        $this->assertCount(6, $instance->etapes);
        $this->assertSame('en_validation', $pap->fresh()->statut);
    }

    public function test_cascade_complete_publie_le_pap(): void
    {
        $pap = $this->pap();
        $instance = $this->service->demarrer($pap, 'PAP', $this->user('directeur'));

        foreach (['chef_service', 'directeur', 'commissaire', 'controleur_financier', 'secretaire_general', 'president'] as $role) {
            $this->service->valider($instance->fresh(), $this->user($role));
        }

        $this->assertSame('valide', $instance->fresh()->statut);
        $this->assertSame('publie', $pap->fresh()->statut);
        $this->assertNotNull($pap->fresh()->publie_le);
    }

    public function test_un_mauvais_role_ne_peut_pas_agir(): void
    {
        $pap = $this->pap();
        $instance = $this->service->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->expectException(RuntimeException::class);
        $this->service->valider($instance, $this->user('president'));
    }

    public function test_rejet_termine_le_circuit(): void
    {
        $pap = $this->pap();
        $instance = $this->service->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->service->rejeter($instance, $this->user('chef_service'), 'Données incomplètes.', 'documentaire');

        $this->assertSame('rejete', $instance->fresh()->statut);
        $this->assertSame('rejete', $pap->fresh()->statut);
    }

    public function test_renvoi_revient_a_l_etape_precedente(): void
    {
        $pap = $this->pap();
        $instance = $this->service->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->service->valider($instance->fresh(), $this->user('chef_service'));
        $this->assertSame(2, $instance->fresh()->etape_courante);

        $this->service->renvoyer($instance->fresh(), $this->user('directeur'), 'À revoir.');
        $this->assertSame(1, $instance->fresh()->etape_courante);
        $this->assertSame('en_attente', $instance->fresh()->etapeCourante()->statut);
    }

    public function test_double_soumission_interdite(): void
    {
        $pap = $this->pap();
        $this->service->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->expectException(RuntimeException::class);
        $this->service->demarrer($pap, 'PAP', $this->user('directeur'));
    }
}
