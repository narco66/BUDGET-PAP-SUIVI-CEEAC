<?php



namespace Tests\Feature;



use App\Models\Budget;

use App\Models\BudgetRevision;

use App\Models\Exercice;

use App\Models\LigneBudgetaire;

use App\Models\Strategie\ProgrammationPluriannuelle;

use App\Models\User;

use App\Services\Budget\BudgetPluriannuelService;

use App\Services\Budget\BudgetRevisionService;

use Database\Seeders\Budget2026Seeder;

use Database\Seeders\BudgetB0Seeder;

use Database\Seeders\BudgetB1Seeder;

use Database\Seeders\BudgetPermissionsSeeder;

use Database\Seeders\ExerciceSeeder;

use Database\Seeders\NomenclatureBudgetaireSeeder;

use Database\Seeders\Pap2026Seeder;

use Database\Seeders\PapP0Seeder;

use Database\Seeders\PlanificationStrategiqueSeeder;

use Database\Seeders\RoleSeeder;

use Database\Seeders\SourceFinancementSeeder;

use Database\Seeders\StructureSeeder;

use Database\Seeders\WorkflowSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;



/** Phase B4 — rectificatifs, virements, lien pluriannuel. */

class BudgetB4Test extends TestCase

{

    use RefreshDatabase;



    private Budget $budget;



    private User $controleur;



    private LigneBudgetaire $ligneA;



    private LigneBudgetaire $ligneB;



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

        $this->seed(PlanificationStrategiqueSeeder::class);



        $this->budget = Budget::firstWhere('exercice_id', Exercice::firstWhere('annee', 2026)->id);

        $this->budget->update(['statut' => 'adopte', 'adopte_le' => now()]);



        $lignes = LigneBudgetaire::where('exercice_id', $this->budget->exercice_id)->orderBy('code')->take(2)->get();

        $this->ligneA = $lignes[0];

        $this->ligneB = $lignes[1];

        $this->ligneA->update(['montant_ouvert' => 100_000_000, 'montant_engage' => 0]);

        $this->ligneB->update(['montant_ouvert' => 50_000_000, 'montant_engage' => 0]);



        $this->controleur = User::factory()->create(['actif' => true])->assignRole('controleur_financier');

    }



    public function test_rectificatif_maj_montant_revise(): void

    {

        $service = app(BudgetRevisionService::class);

        $revision = $service->creerRectificatif(

            $this->budget,

            $this->ligneA->id,

            90_000_000,

            'Ajustement post-adoption test',

            $this->controleur->id,

        );

        $service->valider($revision, $this->controleur->id);



        $this->assertSame('valide', $revision->fresh()->statut);

        $this->assertEquals(90_000_000, (float) $this->ligneA->fresh()->montant_revise);

        $this->assertEquals(100_000_000, (float) $this->ligneA->fresh()->montant_ouvert);

    }



    public function test_virement_equilibre(): void

    {

        $service = app(BudgetRevisionService::class);

        $revision = $service->creerVirement(

            $this->budget,

            $this->ligneA->id,

            $this->ligneB->id,

            10_000_000,

            'Virement inter-lignes test',

            $this->controleur->id,

        );

        $service->valider($revision, $this->controleur->id);



        $this->assertEquals(90_000_000, (float) $this->ligneA->fresh()->montant_revise);

        $this->assertEquals(60_000_000, (float) $this->ligneB->fresh()->montant_revise);

        $this->assertSame(0.0, (float) $revision->mouvements->sum('delta'));

    }



    public function test_virement_refuse_si_montant_insuffisant(): void

    {

        $this->ligneA->update(['montant_engage' => 95_000_000]);



        $this->expectException(\RuntimeException::class);

        app(BudgetRevisionService::class)->creerVirement(

            $this->budget,

            $this->ligneA->id,

            $this->ligneB->id,

            10_000_000,

            'Virement impossible',

            $this->controleur->id,

        );

    }



    public function test_lien_programmation_pluriannuelle(): void

    {

        $pluri = ProgrammationPluriannuelle::firstWhere('reference', 'PROG-PLURI-2026-2030');

        $this->assertNotNull($pluri);



        app(BudgetPluriannuelService::class)->lier($this->budget, $pluri->id, null, $this->controleur->id);



        $this->assertSame($pluri->id, $this->budget->fresh()->programmation_pluriannuelle_id);



        $rapprochement = app(BudgetPluriannuelService::class)->comparer($this->budget->fresh());

        $this->assertArrayHasKey('montant_budget', $rapprochement);

        $this->assertArrayHasKey('enveloppe_pluriannuelle', $rapprochement);

    }



    public function test_ui_revisions_accessible(): void

    {

        $this->actingAs($this->controleur)

            ->get(route('budget.enveloppe.revisions', $this->budget))

            ->assertOk()

            ->assertSee('Historique des révisions');

    }



    public function test_seeder_budget_b4(): void

    {

        $this->seed(\Database\Seeders\BudgetB4Seeder::class);



        $this->assertNotNull($this->budget->fresh()->programmation_pluriannuelle_id);

    }

}

