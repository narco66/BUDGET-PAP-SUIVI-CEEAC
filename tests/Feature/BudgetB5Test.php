<?php



namespace Tests\Feature;



use App\Models\Budget;

use App\Models\Exercice;

use App\Models\User;

use App\Services\Budget\BudgetAuditService;

use App\Services\Budget\BudgetReportService;

use Database\Seeders\Budget2026Seeder;

use Database\Seeders\BudgetB0Seeder;

use Database\Seeders\BudgetB1Seeder;

use Database\Seeders\BudgetB2Seeder;

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

use Illuminate\Support\Facades\Storage;

use Tests\TestCase;



/** Phase B5 — qualité : alias permissions, rapport, audit cycle, exports. */

class BudgetB5Test extends TestCase

{

    use RefreshDatabase;



    private User $gestionnaire;



    private User $lecteur;



    private Budget $budget;



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

        $this->seed(BudgetB2Seeder::class);



        $this->budget = Budget::firstWhere('exercice_id', Exercice::firstWhere('annee', 2026)->id);

        $this->gestionnaire = User::factory()->create(['actif' => true])->assignRole('gestionnaire_budgetaire');

        $this->lecteur = User::factory()->create(['actif' => true])->assignRole('lecteur');

    }



    public function test_alias_permissions_budget(): void

    {

        $this->assertTrue($this->gestionnaire->can('budget.view'));

        $this->assertTrue($this->gestionnaire->can('budget.consolidate'));

        $this->assertTrue($this->gestionnaire->can('budget.simulate'));

        $this->assertTrue($this->gestionnaire->can('budget.report'));

        $this->assertFalse($this->lecteur->can('budget.create'));

        $this->assertTrue($this->lecteur->can('budget.view'));

    }



    public function test_audit_contient_cycle_institutionnel(): void

    {

        $audit = app(BudgetAuditService::class)->executer($this->budget->exercice_id);



        $this->assertArrayHasKey('cycle_institutionnel', $audit);

        $this->assertArrayHasKey('enveloppe_statut', $audit['cycle_institutionnel']);

        $this->assertSame('brouillon', $audit['cycle_institutionnel']['enveloppe_statut']);

    }



    public function test_rapport_service_synthese(): void

    {

        $rapport = app(BudgetReportService::class)->synthese($this->budget->exercice_id);



        $this->assertSame(2026, $rapport['exercice']['annee']);

        $this->assertNotNull($rapport['enveloppe']);

        $this->assertArrayHasKey('conformite', $rapport);

        $this->assertGreaterThan(0, $rapport['preparation']['total']);

    }



    public function test_commande_budget_rapport_json(): void

    {

        $this->artisan('budget:rapport', ['--exercice' => 2026, '--json' => true])

            ->assertSuccessful();

    }



    public function test_ui_rapport_accessible(): void

    {

        $this->actingAs($this->gestionnaire)

            ->get(route('budget.enveloppe.rapport', $this->budget))

            ->assertOk()

            ->assertSee('Checklist cycle');

    }



    public function test_export_rapport_institutionnel_pdf(): void

    {

        Storage::fake('local');

        $admin = User::factory()->create(['actif' => true])->assignRole('administrateur');

        $exercice = Exercice::firstWhere('annee', 2026);



        $this->actingAs($admin)

            ->get(route('reporting.generer', [

                'type' => 'budget_institutionnel',

                'format' => 'pdf',

                'exercice' => $exercice->id,

            ]))

            ->assertOk();

    }



    public function test_documentation_b5_presente(): void

    {

        $this->assertFileExists(base_path('docs/manuel-utilisateur-budget.md'));

        $this->assertFileExists(base_path('docs/audit-module-budget.md'));

    }

}

