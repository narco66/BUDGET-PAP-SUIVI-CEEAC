<?php

namespace Tests\Feature;

use App\Models\AppelFonds;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Recettes\AppelFondsService;
use App\Services\Recettes\RecettesReportingService;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase R5 — états & rapports Recettes (PDF / Excel / CSV) + gating export. */
class RecettesReportingR5Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private EtatMembre $etat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->etat = EtatMembre::create(['code' => 'GAB', 'pays' => 'Gabon', 'cle_repartition' => 12, 'actif' => true]);

        $appel = app(AppelFondsService::class)->emettre([
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-GAB-1',
            'objet' => 'Contribution 2026',
            'montant_appele' => 10_000_000,
            'date_emission' => '2026-01-10',
            'date_echeance' => '2026-02-01',
            'taux_penalite' => 5,
        ], User::factory()->create()->assignRole('gestionnaire_budgetaire'));
        app(AppelFondsService::class)->enregistrerRecouvrement($appel, 3_000_000);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_service_calcule_la_mobilisation(): void
    {
        $service = app(RecettesReportingService::class);
        $rows = $service->mobilisationParEtat($this->exercice->id);
        $gabon = $rows->firstWhere('code', 'GAB');

        $this->assertEquals(10_000_000.0, $gabon->appele);
        $this->assertEquals(3_000_000.0, $gabon->recouvre);
        $this->assertEquals(7_000_000.0, $gabon->arriere);
        $this->assertEquals(30.0, $gabon->taux);

        $synthese = $service->syntheseMobilisation($this->exercice->id);
        $this->assertEquals(7_000_000.0, $synthese['arriere']);
    }

    public function test_arrieres_liste_les_appels_non_soldes(): void
    {
        $appels = app(RecettesReportingService::class)->arrieres($this->exercice->id);
        $this->assertCount(1, $appels);
        $this->assertEquals(7_000_000.0, (float) $appels->first()->solde);
    }

    public function test_export_excel_mobilisation(): void
    {
        $user = $this->user('gestionnaire_budgetaire');
        $response = $this->actingAs($user)->get(route('recettes.rapports.mobilisation.excel', ['exercice' => $this->exercice->id]));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_export_csv_encaissements(): void
    {
        $user = $this->user('comptable');
        $response = $this->actingAs($user)->get(route('recettes.rapports.encaissements.csv', ['exercice' => $this->exercice->id]));
        $response->assertOk();
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));
    }

    public function test_pdf_mobilisation(): void
    {
        $user = $this->user('gestionnaire_budgetaire');
        $response = $this->actingAs($user)->get(route('recettes.rapports.mobilisation.pdf', ['exercice' => $this->exercice->id]));
        $response->assertOk();
        $this->assertStringContainsString('pdf', $response->headers->get('content-type'));
    }

    public function test_export_refuse_sans_permission(): void
    {
        // 'point_focal' n'a pas recette.exporter (ni recette.voir).
        $user = $this->user('point_focal');
        $this->actingAs($user)->get(route('recettes.rapports.mobilisation.excel'))->assertForbidden();
    }
}
