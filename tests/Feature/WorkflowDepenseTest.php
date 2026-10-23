<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Services\WorkflowService;
use App\Support\WorkflowRejetCategorie;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Circuit BESOIN — expression du besoin (P1). */
class WorkflowDepenseTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private WorkflowService $service;
    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->service = app(WorkflowService::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function besoin(): ExpressionBesoin
    {
        $user = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-BESOIN', 500_000);

        return ExpressionBesoin::create([
            'numero' => 'EB-WF-1',
            'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => $user->id,
            'objet' => 'Besoin workflow test',
            'montant_estime' => 100_000,
            'priorite' => 'moyenne',
            'statut' => 'brouillon',
        ]);
    }

    public function test_circuit_besoin_trois_etapes(): void
    {
        $besoin = $this->besoin();
        $instance = $this->service->demarrer($besoin, 'BESOIN', $this->user('gestionnaire_budgetaire'));

        $this->assertCount(3, $instance->etapes);
        $this->assertSame('soumis', $besoin->fresh()->statut);
    }

    public function test_validation_complete_valide_le_besoin(): void
    {
        $besoin = $this->besoin();
        $instance = $this->service->demarrer($besoin, 'BESOIN', $this->user('gestionnaire_budgetaire'));

        foreach (['chef_service', 'directeur', 'controleur_financier'] as $role) {
            $this->service->valider($instance->fresh(), $this->user($role));
        }

        $this->assertSame('valide', $instance->fresh()->statut);
        $this->assertSame('valide', $besoin->fresh()->statut);
    }

    public function test_rejet_besoin_avec_categorie(): void
    {
        $besoin = $this->besoin();
        $instance = $this->service->demarrer($besoin, 'BESOIN', $this->user('gestionnaire_budgetaire'));

        $this->service->rejeter(
            $instance,
            $this->user('chef_service'),
            'Montant non justifié.',
            WorkflowRejetCategorie::BUDGETAIRE,
        );

        $this->assertSame('rejete', $besoin->fresh()->statut);
    }
}
