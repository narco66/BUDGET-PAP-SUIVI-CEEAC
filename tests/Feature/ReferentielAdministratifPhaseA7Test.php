<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\InstitutionGouvernanceService;
use App\Services\Institution\WorkflowInstitutionResolver;
use App\Services\WorkflowService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** Phase A7 — workflows alignés responsables, délégations et intérims. */
class ReferentielAdministratifPhaseA7Test extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflow;

    private Structure $service;

    private Structure $direction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->workflow = app(WorkflowService::class);

        $departement = Structure::create([
            'code' => 'DEP-A7',
            'libelle' => 'Departement A7',
            'type' => 'departement',
            'actif' => true,
        ]);
        $this->direction = Structure::create([
            'code' => 'DIR-A7',
            'libelle' => 'Direction A7',
            'type' => 'direction',
            'parent_id' => $departement->id,
            'actif' => true,
        ]);
        $this->service = Structure::create([
            'code' => 'SVC-A7',
            'libelle' => 'Service A7',
            'type' => 'service',
            'parent_id' => $this->direction->id,
            'actif' => true,
        ]);
    }

    /** @return array{0: ExpressionBesoin, 1: User} */
    private function besoinSoumis(?User $responsableDirection = null): array
    {
        $chef = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('chef_service');
        $this->service->update(['responsable_user_id' => $chef->id]);

        if ($responsableDirection) {
            $this->direction->update(['responsable_user_id' => $responsableDirection->id]);
        }

        $demandeur = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $exercice = Exercice::factory()->create();
        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-A7-001',
            'exercice_id' => $exercice->id,
            'structure_id' => $this->service->id,
            'demandeur_id' => $demandeur->id,
            'objet' => 'Besoin A7',
            'montant_estime' => 5000,
            'statut' => 'brouillon',
        ]);

        $this->workflow->demarrer($besoin, 'BESOIN', $demandeur);
        $this->workflow->valider($besoin->fresh()->workflowActif, $chef);

        return [$besoin->fresh(), $demandeur];
    }

    public function test_responsable_direction_peut_valider_sans_role_directeur(): void
    {
        $responsable = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        [$besoin] = $this->besoinSoumis($responsable);
        $instance = $besoin->workflowActif;

        $this->assertTrue($this->workflow->peutAgir($instance, $responsable));
        $this->workflow->valider($instance, $responsable);

        $this->assertSame('soumis', $besoin->fresh()->statut);
        $this->assertSame(3, $instance->fresh()->etape_courante);
    }

    public function test_delegue_peut_valider_a_la_place_du_responsable(): void
    {
        $responsable = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $delegue = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        [$besoin] = $this->besoinSoumis($responsable);

        app(InstitutionGouvernanceService::class)->creerDelegation([
            'structure_id' => $this->direction->id,
            'delegue_par' => $responsable->id,
            'delegue_a' => $delegue->id,
            'date_debut' => now()->toDateString(),
            'motif' => 'Mission',
        ]);

        $instance = $besoin->workflowActif;
        $this->assertTrue($this->workflow->peutAgir($instance, $delegue));
        $this->workflow->valider($instance, $delegue);

        $this->assertSame('soumis', $besoin->fresh()->statut);
        $this->assertSame(3, $instance->fresh()->etape_courante);
    }

    public function test_interim_peut_valider_a_la_place_du_responsable(): void
    {
        $responsable = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $interim = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        [$besoin] = $this->besoinSoumis($responsable);

        app(InstitutionGouvernanceService::class)->creerInterim([
            'structure_id' => $this->direction->id,
            'titulaire_user_id' => $responsable->id,
            'interim_user_id' => $interim->id,
            'date_debut' => now()->toDateString(),
        ]);

        $instance = $besoin->workflowActif;
        $this->assertTrue($this->workflow->peutAgir($instance, $interim));
        $this->workflow->valider($instance, $interim);

        $this->assertSame('soumis', $besoin->fresh()->statut);
        $this->assertSame(3, $instance->fresh()->etape_courante);
    }

    public function test_utilisateur_non_habilite_ne_peut_pas_valider(): void
    {
        $responsable = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        [$besoin] = $this->besoinSoumis($responsable);
        $intrus = User::factory()->create(['actif' => true, 'statut' => 'actif']);

        $this->assertFalse($this->workflow->peutAgir($besoin->workflowActif, $intrus));

        $this->expectException(RuntimeException::class);
        $this->workflow->valider($besoin->workflowActif, $intrus);
    }

    public function test_role_directeur_conserve_l_habilitation_classique(): void
    {
        $responsable = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        [$besoin] = $this->besoinSoumis($responsable);
        $directeur = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('directeur');

        $this->assertTrue($this->workflow->peutAgir($besoin->workflowActif, $directeur));
    }

    public function test_circuit_pap_reste_base_sur_les_roles(): void
    {
        $exercice = Exercice::create(['annee' => 2027, 'libelle' => 'Ex 2027', 'statut' => 'ouvert']);
        $pap = \App\Models\Pap::create(['exercice_id' => $exercice->id, 'libelle' => 'PAP A7', 'statut' => 'brouillon']);
        $instance = $this->workflow->demarrer($pap, 'PAP', User::factory()->create(['actif' => true])->assignRole('directeur'));

        $chef = User::factory()->create(['actif' => true])->assignRole('chef_service');
        $president = User::factory()->create(['actif' => true])->assignRole('president');

        $this->assertTrue($this->workflow->peutAgir($instance, $chef));
        $this->assertFalse($this->workflow->peutAgir($instance, $president));
    }

    public function test_resolver_identifie_la_direction_validateuse(): void
    {
        $responsable = User::factory()->create(['actif' => true]);
        $this->direction->update(['responsable_user_id' => $responsable->id]);

        $structure = app(WorkflowInstitutionResolver::class)->structureValidateur($this->service->id, 'directeur');

        $this->assertNotNull($structure);
        $this->assertSame('DIR-A7', $structure->code);
    }
}
