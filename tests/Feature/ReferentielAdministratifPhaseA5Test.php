<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\CadreOrganiqueCeeacService;
use App\Services\Institution\InstitutionExportService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** Phase A5 — exports Excel/PDF institutionnels. */
class ReferentielAdministratifPhaseA5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(StructureSeeder::class);
    }

    private function directeur(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('directeur');
    }

    public function test_export_annuaire_excel(): void
    {
        Excel::fake();
        $suffix = now()->format('Ymd');

        $this->actingAs($this->directeur())
            ->get(route('structure.exporter.annuaire.excel'))
            ->assertOk();

        Excel::assertDownloaded('annuaire-institutionnel-'.$suffix.'.xlsx');
    }

    public function test_export_annuaire_pdf(): void
    {
        $response = $this->actingAs($this->directeur())
            ->get(route('structure.exporter.annuaire.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_export_refuse_sans_permission(): void
    {
        $user = User::factory()->create(['actif' => true])->assignRole('point_focal');

        $this->actingAs($user)->get(route('structure.exporter.annuaire.excel'))->assertForbidden();
        $this->actingAs($user)->get(route('structure.exporter.annuaire.pdf'))->assertForbidden();
    }

    public function test_export_respecte_perimetre_structure(): void
    {
        $depOk = Structure::where('code', 'DAPPS')->firstOrFail();
        $depKo = Structure::where('code', 'DATI')->firstOrFail();

        $user = User::factory()->create(['actif' => true])->assignRole('directeur');
        $user->accessScopes()->create(['type' => 'departement', 'scope_id' => $depOk->id]);

        $lignes = app(InstitutionExportService::class)->annuairePourExport($user);

        $this->assertTrue($lignes->contains(fn ($l) => $l['code'] === 'DAPPS'));
        $this->assertFalse($lignes->contains(fn ($l) => $l['code'] === 'DATI'));
    }

    public function test_ecrans_affichent_boutons_export(): void
    {
        $user = $this->directeur();

        $this->actingAs($user)->get(route('structures.index'))->assertOk()->assertSee('Annuaire Excel');
        $this->actingAs($user)->get(route('organigramme'))->assertOk()->assertSee('Annuaire PDF');
        $this->actingAs($user)->get(route('institution.dashboard'))->assertOk()->assertSee('Exporter');
    }

    public function test_service_annuaire_contient_lineage(): void
    {
        app(CadreOrganiqueCeeacService::class)->synchroniser();

        $ligne = app(InstitutionExportService::class)
            ->annuairePourExport(User::factory()->create()->assignRole('administrateur'))
            ->firstWhere('code', 'SVC-BUDGET');

        $this->assertNotNull($ligne);
        $this->assertSame('DSG', $ligne['departement_code']);
        $this->assertSame('DIR-PPB', $ligne['direction_code']);
    }
}
