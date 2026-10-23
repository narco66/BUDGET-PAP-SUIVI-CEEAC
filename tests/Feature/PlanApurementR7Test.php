<?php

namespace Tests\Feature;

use App\Models\AppelFonds;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Recettes\AppelFondsService;
use App\Services\Recettes\PlanApurementService;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** Phase R7 — plans d'apurement : échéancier, règlement, report sur l'appel, soldé. */
class PlanApurementR7Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private EtatMembre $etat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->etat = EtatMembre::create(['code' => 'COD', 'pays' => 'RDC', 'cle_repartition' => 15, 'actif' => true]);
    }

    private function appel(): AppelFonds
    {
        return app(AppelFondsService::class)->emettre([
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-'.uniqid(),
            'objet' => 'Contribution 2026',
            'montant_appele' => 9_000_000,
            'date_emission' => '2026-01-10',
            'date_echeance' => '2026-02-01',
        ], User::factory()->create()->assignRole('gestionnaire_budgetaire'));
    }

    public function test_tables_existent(): void
    {
        $this->assertTrue(Schema::hasTable('plans_apurement'));
        $this->assertTrue(Schema::hasTable('echeances_apurement'));
    }

    public function test_creation_repartit_le_solde_en_echeances(): void
    {
        $plan = app(PlanApurementService::class)->creer($this->appel(), [
            'nombre_echeances' => 3, 'date_debut' => '2026-03-01',
        ]);

        $this->assertCount(3, $plan->echeances);
        $this->assertEquals(9_000_000.0, (float) $plan->montant_total);
        // La somme des échéances boucle exactement le total.
        $this->assertEquals(9_000_000.0, (float) $plan->echeances->sum('montant_prevu'));
    }

    public function test_un_seul_plan_actif_par_appel(): void
    {
        $service = app(PlanApurementService::class);
        $appel = $this->appel();
        $service->creer($appel, ['nombre_echeances' => 2, 'date_debut' => '2026-03-01']);

        $this->expectException(RuntimeException::class);
        $service->creer($appel->fresh(), ['nombre_echeances' => 2, 'date_debut' => '2026-04-01']);
    }

    public function test_reglement_echeance_reporte_sur_appel_et_solde_le_plan(): void
    {
        $service = app(PlanApurementService::class);
        $appel = $this->appel();
        $plan = $service->creer($appel, ['nombre_echeances' => 2, 'date_debut' => '2026-03-01']);

        foreach ($plan->echeances as $ech) {
            $service->reglerEcheance($ech, (float) $ech->montant_prevu);
        }

        $plan->refresh();
        $appel->refresh();
        $this->assertSame('solde', $plan->statut);
        $this->assertEquals(0.0, $plan->solde());
        // Report intégral sur l'appel de fonds (source unique du réalisé).
        $this->assertEquals(9_000_000.0, (float) $appel->montant_regle);
        $this->assertSame('solde', $appel->statut);
    }

    public function test_reglement_ne_depasse_pas_l_echeance(): void
    {
        $service = app(PlanApurementService::class);
        $plan = $service->creer($this->appel(), ['nombre_echeances' => 3, 'date_debut' => '2026-03-01']);
        $ech = $plan->echeances->first();

        $this->expectException(RuntimeException::class);
        $service->reglerEcheance($ech, (float) $ech->montant_prevu + 1);
    }

    public function test_creation_via_http_gated(): void
    {
        $appel = $this->appel();

        $this->actingAs(User::factory()->create()->assignRole('lecteur'))
            ->post(route('plans-apurement.store', $appel), ['nombre_echeances' => 3, 'date_debut' => '2026-03-01'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create()->assignRole('gestionnaire_budgetaire'))
            ->post(route('plans-apurement.store', $appel), ['nombre_echeances' => 3, 'date_debut' => '2026-03-01'])
            ->assertRedirect();

        $this->assertNotNull($appel->fresh()->planApurementActif());
    }
}
