<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\CadreOrganiqueCeeacService;
use App\Services\Institution\StructureHierarchieService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase A1 — enrichissement cadre CEEAC + validation hiérarchique. */
class ReferentielAdministratifPhaseA1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        app(CadreOrganiqueCeeacService::class)->synchroniser();
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_departements_metiers_ont_des_directions(): void
    {
        foreach (['DAPPS', 'DMCAEMF', 'DERNADR', 'DATI', 'DPGDHS'] as $codeDept) {
            $dept = Structure::where('code', $codeDept)->firstOrFail();
            $this->assertTrue(
                Structure::where('type', 'direction')->where('parent_id', $dept->id)->exists(),
                "Le département {$codeDept} devrait avoir au moins une direction."
            );
        }

        $this->assertDatabaseHas('structures', ['code' => 'DIR-APPS-GOUV', 'type' => 'direction']);
        $this->assertDatabaseHas('structures', ['code' => 'SVC-INFRA-ROUT', 'type' => 'service']);
    }

    public function test_service_sous_dati_a_lineage_coherent(): void
    {
        $service = Structure::where('code', 'SVC-INFRA-ROUT')->firstOrFail();

        $this->assertSame('DIR-INFRA', $service->direction()?->code);
        $this->assertSame('DATI', $service->departement()?->code);
    }

    public function test_validation_refuse_service_sans_direction_parent(): void
    {
        $dept = Structure::where('code', 'DAPPS')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('structures.store'), [
                'code' => 'SVC-INVALIDE',
                'libelle' => 'Service invalide',
                'type' => 'service',
                'parent_id' => $dept->id,
                'actif' => 1,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_validation_accepte_direction_sous_departement(): void
    {
        $dept = Structure::where('code', 'DAPPS')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('structures.store'), [
                'code' => 'DIR-TEST-A1',
                'libelle' => 'Direction test A1',
                'type' => 'direction',
                'parent_id' => $dept->id,
                'actif' => 1,
            ])
            ->assertRedirect(route('structures.index'));

        $this->assertDatabaseHas('structures', [
            'code' => 'DIR-TEST-A1',
            'parent_id' => $dept->id,
        ]);
    }

    public function test_audit_cadre_signale_departement_sans_direction(): void
    {
        Structure::create([
            'code' => 'DEPT-VIDE',
            'libelle' => 'Departement vide',
            'type' => 'departement',
            'parent_id' => Structure::where('code', 'PRES')->value('id'),
            'niveau' => 1,
            'actif' => true,
        ]);

        $sansDirection = app(StructureHierarchieService::class)->departementsSansDirections();

        $this->assertTrue(collect($sansDirection)->contains(fn ($l) => str_contains($l, 'DEPT-VIDE')));
    }

    public function test_commande_audit_reussit_apres_sync_a1(): void
    {
        $this->artisan('institution:audit-cadre')->assertSuccessful();
    }
}
