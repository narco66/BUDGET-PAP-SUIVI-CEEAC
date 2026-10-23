<?php



namespace Tests\Feature;



use App\Models\Budget;

use App\Models\BudgetConsolidation;

use App\Models\BudgetPreparation;

use App\Models\BudgetSimulation;

use App\Models\Exercice;

use App\Models\User;

use App\Services\Budget\BudgetConsolidationService;

use App\Services\Budget\BudgetPreparationService;

use App\Services\Budget\BudgetSimulationService;

use Database\Seeders\Budget2026Seeder;

use Database\Seeders\BudgetB0Seeder;

use Database\Seeders\BudgetB1Seeder;

use Database\Seeders\BudgetPermissionsSeeder;

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



/** Phase B3 — snapshots consolidation et scénarios comparables. */

class BudgetB3Test extends TestCase

{

    use RefreshDatabase;



    private Budget $budget;



    private User $directeur;



    protected function setUp(): void

    {

        parent::setUp();

        $this->seed([

            RoleSeeder::class,

            BudgetPermissionsSeeder::class,

            StructureSeeder::class,

            ExerciceSeeder::class,

            SourceFinancementSeeder::class,

            NomenclatureBudgetaireSeeder::class,

            Pap2026Seeder::class,

            Budget2026Seeder::class,

            WorkflowSeeder::class,

        ]);

        $this->seed(PapP0Seeder::class);

        $this->seed(BudgetB0Seeder::class);

        $this->seed(BudgetB1Seeder::class);



        $this->budget = Budget::firstWhere('exercice_id', Exercice::firstWhere('annee', 2026)->id);

        $this->directeur = User::factory()->create(['actif' => true])->assignRole('directeur');

    }



    public function test_consolidation_persiste_snapshot(): void

    {

        app(BudgetPreparationService::class)->initialiser($this->budget);

        $prep = BudgetPreparation::where('budget_id', $this->budget->id)->first();

        $prep->update(['statut' => 'arbitre', 'montant_arbitre' => 80_000_000]);



        $stats = app(BudgetConsolidationService::class)->consolider($this->budget, $this->directeur->id);



        $this->assertSame(1, BudgetConsolidation::where('budget_id', $this->budget->id)->count());

        $consol = BudgetConsolidation::first();

        $this->assertSame('applique', $consol->statut);

        $this->assertNotEmpty($consol->snapshot);

        $this->assertSame(1, $stats['version']);

    }



    public function test_simulation_globale_et_comparaison(): void

    {

        app(BudgetPreparationService::class)->initialiser($this->budget);



        $service = app(BudgetSimulationService::class);



        $simA = $service->creer($this->budget, [

            'libelle' => 'Test -5 %',

            'type' => 'global_pct',

            'parametres' => ['taux' => -5],

        ], $this->directeur->id);

        $service->calculer($simA, $this->directeur->id);



        $simB = $service->creer($this->budget, [

            'libelle' => 'Test +10 %',

            'type' => 'global_pct',

            'parametres' => ['taux' => 10],

        ], $this->directeur->id);

        $service->calculer($simB, $this->directeur->id);



        $this->assertEqualsWithDelta(-5, (float) $simA->fresh()->ecart_pct, 0.1);

        $this->assertEqualsWithDelta(10, (float) $simB->fresh()->ecart_pct, 0.1);



        $comparaison = $service->comparer($this->budget, [$simA->id, $simB->id]);

        $this->assertCount(2, $comparaison['scenarios']);

        $this->assertGreaterThan($comparaison['ecart_min'], $comparaison['ecart_max']);

    }



    public function test_retenir_scenario(): void

    {

        app(BudgetPreparationService::class)->initialiser($this->budget);

        $service = app(BudgetSimulationService::class);



        $sim = $service->creer($this->budget, [

            'libelle' => 'Scénario retenu',

            'type' => 'global_pct',

            'parametres' => ['taux' => 0],

        ], $this->directeur->id);

        $service->calculer($sim, $this->directeur->id);

        $service->retenir($sim->fresh(), $this->directeur->id);



        $this->assertSame('retenu', $sim->fresh()->statut);

    }



    public function test_ui_simulations_accessible(): void

    {

        app(BudgetPreparationService::class)->initialiser($this->budget);



        $this->actingAs($this->directeur)

            ->get(route('budget.enveloppe.simulations', $this->budget))

            ->assertOk()

            ->assertSee('Scénarios comparables');

    }



    public function test_seeder_budget_b3(): void

    {

        app(BudgetPreparationService::class)->initialiser($this->budget);

        $this->seed(\Database\Seeders\BudgetB3Seeder::class);



        $this->assertGreaterThanOrEqual(2, BudgetSimulation::where('budget_id', $this->budget->id)->count());

    }

}

