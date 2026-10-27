<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Pap;
use App\Models\User;
use App\Services\Pap\PapPilotageService;
use Database\Seeders\Budget2026Seeder;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\Pap2026Seeder;
use Database\Seeders\PapP0Seeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SourceFinancementSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P1 — dashboard pilotage PAP et performance activités/produits en UI. */
class PapP1Test extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            StructureSeeder::class,
            ExerciceSeeder::class,
            SourceFinancementSeeder::class,
            NomenclatureBudgetaireSeeder::class,
            Pap2026Seeder::class,
            Budget2026Seeder::class,
            WorkflowSeeder::class,
        ]);
        $this->gestionnaire = User::factory()->create(['actif' => true]);
        $this->gestionnaire->assignRole('directeur');
        $this->seed(PapP0Seeder::class);
    }

    public function test_pilotage_service_expose_produits_et_alertes(): void
    {
        $exerciceId = Exercice::firstWhere('annee', 2026)->id;
        $tableau = app(PapPilotageService::class)->tableau($exerciceId);

        $this->assertGreaterThan(0, $tableau['produits']->count());
        $this->assertGreaterThan(0, $tableau['activites']->count());
        $this->assertGreaterThan(0, $tableau['piliers']->count());
        $this->assertArrayHasKey('indice_global', $tableau['indicateurs']);
    }

    public function test_dashboard_pap_accessible(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('paps.dashboard', ['exercice' => 2026]))
            ->assertOk()
            ->assertSee('Pilotage du Plan Annuel de Performance')
            ->assertSee('Importer PAP')
            ->assertSee(route('paps.dashboard.import'), false)
            ->assertSee('Performance par pilier')
            ->assertSee('Produits');
    }

    public function test_fiche_pap_affiche_onglets_performance(): void
    {
        $pap = Pap::firstWhere('exercice_id', Exercice::firstWhere('annee', 2026)->id);

        $this->actingAs($this->gestionnaire)
            ->get(route('paps.show', $pap))
            ->assertOk()
            ->assertSee('Produits')
            ->assertSee('Activités')
            ->assertSee('Alertes')
            ->assertSee('ACT-2011');
    }

    public function test_dashboard_filtre_pilier(): void
    {
        $response = $this->actingAs($this->gestionnaire)
            ->get(route('paps.dashboard', ['exercice' => 2026, 'pilier' => '201']))
            ->assertOk()
            ->assertSee('Retirer le filtre');

        // Le filtre repose sur le pilier de rattachement, pas sur un préfixe de code.
        $produits = $response->viewData('produitsFiltres');
        $activites = $response->viewData('activitesFiltrees');
        $this->assertGreaterThan(0, $produits->count());
        $this->assertGreaterThan(0, $activites->count());
        $this->assertTrue($produits->every(fn (array $p) => $p['pilier_code'] === '201'));
        $this->assertTrue($activites->every(fn (array $a) => $a['pilier_code'] === '201'));
    }

    public function test_dashboard_ignore_pilier_inconnu(): void
    {
        $response = $this->actingAs($this->gestionnaire)
            ->get(route('paps.dashboard', ['exercice' => 2026, 'pilier' => 'INCONNU']))
            ->assertOk()
            ->assertDontSee('Retirer le filtre');

        $this->assertNull($response->viewData('pilierFiltre'));
    }

    public function test_liste_pap_lien_pilotage(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('paps.index'))
            ->assertOk()
            ->assertSee('Pilotage');
    }
}
