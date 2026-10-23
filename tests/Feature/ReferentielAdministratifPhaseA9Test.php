<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\InstitutionExportService;
use App\Services\Institution\InstitutionGouvernanceService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** Phase A9 — tests API, exports, délégations et périmètres institutionnels. */
class ReferentielAdministratifPhaseA9Test extends TestCase
{
    use RefreshDatabase;

    private Structure $depAutorise;

    private Structure $depRefuse;

    private Structure $direction;

    private Structure $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WorkflowSeeder::class);

        $this->depAutorise = Structure::create([
            'code' => 'DEP-A9-OK',
            'libelle' => 'Departement autorise A9',
            'type' => 'departement',
            'actif' => true,
        ]);
        $this->depRefuse = Structure::create([
            'code' => 'DEP-A9-KO',
            'libelle' => 'Departement refuse A9',
            'type' => 'departement',
            'actif' => true,
        ]);
        $this->direction = Structure::create([
            'code' => 'DIR-A9',
            'libelle' => 'Direction A9',
            'type' => 'direction',
            'parent_id' => $this->depAutorise->id,
            'actif' => true,
        ]);
        $this->service = Structure::create([
            'code' => 'SVC-A9',
            'libelle' => 'Service A9',
            'type' => 'service',
            'parent_id' => $this->direction->id,
            'actif' => true,
        ]);
    }

    private function utilisateurPerimetre(): User
    {
        $user = User::factory()->create([
            'actif' => true,
            'structure_id' => $this->service->id,
        ])->assignRole('chef_service');
        $user->accessScopes()->create(['type' => 'departement', 'scope_id' => $this->depAutorise->id]);

        return $user;
    }

    public function test_api_structures_exige_authentification(): void
    {
        $this->getJson(route('api.structures'))->assertUnauthorized();
    }

    public function test_api_structures_retourne_lineage(): void
    {
        Sanctum::actingAs(User::factory()->create(['actif' => true]), ['lecture']);

        $response = $this->getJson(route('api.structures', ['code' => 'SVC-A9']))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['code', 'departement', 'direction', 'service'],
                ],
            ]);

        $ligne = collect($response->json('data'))->firstWhere('code', 'SVC-A9');
        $this->assertSame('DEP-A9-OK', $ligne['departement']['code']);
        $this->assertSame('DIR-A9', $ligne['direction']['code']);
        $this->assertSame('SVC-A9', $ligne['service']['code']);
    }

    public function test_api_structures_respecte_perimetre(): void
    {
        Sanctum::actingAs($this->utilisateurPerimetre(), ['lecture']);

        $codes = collect($this->getJson(route('api.structures'))->json('data'))->pluck('code');

        $this->assertTrue($codes->contains('DEP-A9-OK'));
        $this->assertTrue($codes->contains('SVC-A9'));
        $this->assertFalse($codes->contains('DEP-A9-KO'));
    }

    public function test_api_institution_filtre_indicateurs_par_perimetre(): void
    {
        Sanctum::actingAs($this->utilisateurPerimetre(), ['lecture']);

        $response = $this->getJson(route('api.institution'))->assertOk();

        $effectifs = collect($response->json('data.indicateurs.effectifs_departements'));
        $this->assertTrue($effectifs->contains(fn ($r) => ($r['code'] ?? $r->code ?? null) === 'DEP-A9-OK'));
        $this->assertFalse($effectifs->contains(fn ($r) => ($r['code'] ?? $r->code ?? null) === 'DEP-A9-KO'));
    }

    public function test_api_gouvernance_expose_delegations_et_interims(): void
    {
        $responsable = User::factory()->create(['actif' => true]);
        $delegue = User::factory()->create(['actif' => true]);
        $interim = User::factory()->create(['actif' => true]);
        $this->direction->update(['responsable_user_id' => $responsable->id]);

        $gouvernance = app(InstitutionGouvernanceService::class);
        $gouvernance->creerDelegation([
            'structure_id' => $this->direction->id,
            'delegue_par' => $responsable->id,
            'delegue_a' => $delegue->id,
            'date_debut' => now()->toDateString(),
        ]);
        $gouvernance->creerInterim([
            'structure_id' => $this->direction->id,
            'titulaire_user_id' => $responsable->id,
            'interim_user_id' => $interim->id,
            'date_debut' => now()->toDateString(),
        ]);

        Sanctum::actingAs($delegue, ['lecture']);

        $response = $this->getJson(route('api.institution.gouvernance'))->assertOk();

        $this->assertNotEmpty($response->json('data.delegations'));
        $this->assertSame('delegation', $response->json('data.delegations.0.type'));

        Sanctum::actingAs($interim, ['lecture']);

        $responseInterim = $this->getJson(route('api.institution.gouvernance'))->assertOk();
        $this->assertNotEmpty($responseInterim->json('data.interims'));
        $this->assertSame('interim', $responseInterim->json('data.interims.0.type'));
    }

    public function test_api_me_inclut_structure_et_perimetre(): void
    {
        $user = $this->utilisateurPerimetre();
        Sanctum::actingAs($user, ['lecture']);

        $this->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonPath('structure_id', $user->structure_id)
            ->assertJsonPath('structure_code', 'SVC-A9')
            ->assertJsonFragment(['structures_autorisees' => app(\App\Services\AccessScopeService::class)->structuresAutorisees($user)->values()->all()]);
    }

    public function test_export_annuaire_respecte_perimetre(): void
    {
        Structure::create([
            'code' => 'DEP-A9-EXTRA',
            'libelle' => 'Departement extra',
            'type' => 'departement',
            'actif' => true,
        ]);

        $user = $this->utilisateurPerimetre();
        $user->givePermissionTo('administratif.exporter');

        $lignes = app(InstitutionExportService::class)->annuairePourExport($user);

        $this->assertTrue($lignes->contains(fn ($l) => $l['code'] === 'DEP-A9-OK'));
        $this->assertFalse($lignes->contains(fn ($l) => $l['code'] === 'DEP-A9-KO'));
        $this->assertFalse($lignes->contains(fn ($l) => $l['code'] === 'DEP-A9-EXTRA'));
    }

    public function test_routes_export_accessibles_avec_permission_administratif(): void
    {
        Excel::fake();
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo('administratif.exporter', 'administratif.voir');

        $this->actingAs($user)->get(route('structure.exporter.annuaire.excel'))->assertOk();
        Excel::assertDownloaded('annuaire-institutionnel-'.now()->format('Ymd').'.xlsx');
    }

    public function test_delegation_active_detectee_par_service_et_api(): void
    {
        $responsable = User::factory()->create(['actif' => true]);
        $delegue = User::factory()->create(['actif' => true]);

        app(InstitutionGouvernanceService::class)->creerDelegation([
            'structure_id' => $this->direction->id,
            'delegue_par' => $responsable->id,
            'delegue_a' => $delegue->id,
            'date_debut' => now()->toDateString(),
        ]);

        $this->assertTrue(
            app(InstitutionGouvernanceService::class)->utilisateurEstDelegueSurStructure($delegue, $this->direction->id)
        );

        Sanctum::actingAs($delegue, ['lecture']);
        $this->getJson(route('api.institution.gouvernance'))
            ->assertOk()
            ->assertJsonPath('data.delegations.0.structure_code', 'DIR-A9');
    }

    public function test_appels_api_institution_sont_journalises(): void
    {
        Sanctum::actingAs(User::factory()->create(['actif' => true]), ['lecture']);

        $this->getJson(route('api.structures'))->assertOk();

        $this->assertDatabaseHas('api_logs', [
            'chemin' => 'api/v1/structures',
            'methode' => 'GET',
            'statut' => 200,
        ]);
    }
}
