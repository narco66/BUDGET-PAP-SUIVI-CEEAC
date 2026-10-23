<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetPreparation;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\User;
use App\Services\Budget\BudgetArbitrageService;
use App\Services\Budget\BudgetConsolidationService;
use App\Services\Budget\BudgetPreparationService;
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

/** Phase B2 — préparation, validation hiérarchique, arbitrage DPPB. */
class BudgetB2Test extends TestCase
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

    public function test_initialise_preparations_depuis_lignes(): void
    {
        $count = app(BudgetPreparationService::class)->initialiser($this->budget);

        $this->assertGreaterThan(0, BudgetPreparation::where('budget_id', $this->budget->id)->count());
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_circuit_validation_et_arbitrage(): void
    {
        app(BudgetPreparationService::class)->initialiser($this->budget);
        $prep = BudgetPreparation::where('budget_id', $this->budget->id)->first();
        $structureId = $prep->structure_id ?? Structure::first()->id;
        $prep->update(['structure_id' => $structureId]);

        $service = User::factory()->create(['actif' => true])->assignRole('chef_service');
        $commissaire = User::factory()->create(['actif' => true])->assignRole('commissaire');
        $dppb = User::factory()->create(['actif' => true])->assignRole('controleur_financier');

        app(BudgetPreparationService::class)->soumettreStructure($this->budget, $structureId, $service->id);
        $this->assertSame('soumis_service', $prep->fresh()->statut);

        app(BudgetPreparationService::class)->validerNiveau($this->budget, 'direction', $this->directeur->id, $structureId);
        $this->assertSame('valide_direction', $prep->fresh()->statut);

        app(BudgetPreparationService::class)->validerNiveau($this->budget, 'departement', $commissaire->id, $structureId);
        $this->assertSame('valide_departement', $prep->fresh()->statut);

        app(BudgetPreparationService::class)->validerNiveau($this->budget, 'dppb', $dppb->id, $structureId);
        $this->assertSame('en_arbitrage', $prep->fresh()->statut);

        app(BudgetArbitrageService::class)->arbitrer($prep->fresh(), 99_000_000, 'Ajustement DPPB test', $dppb->id);
        $this->assertSame('arbitre', $prep->fresh()->statut);
        $this->assertEquals(99_000_000, (float) $prep->fresh()->montant_arbitre);
    }

    public function test_consolidation_applique_montants(): void
    {
        app(BudgetPreparationService::class)->initialiser($this->budget);
        $prep = BudgetPreparation::where('budget_id', $this->budget->id)->first();
        $ligne = $prep->ligneBudgetaire;

        $prep->update(['statut' => 'arbitre', 'montant_arbitre' => 75_000_000]);

        $stats = app(BudgetConsolidationService::class)->consolider($this->budget, $this->directeur->id);

        $this->assertSame(1, $stats['lignes']);
        $this->assertSame('consolide', $prep->fresh()->statut);
        $this->assertEquals(75_000_000, (float) $ligne->fresh()->montant_ouvert);
    }

    public function test_ui_preparations_accessible(): void
    {
        app(BudgetPreparationService::class)->initialiser($this->budget);

        $this->actingAs($this->directeur)
            ->get(route('budget.enveloppe.preparations', $this->budget))
            ->assertOk()
            ->assertSee('Propositions par ligne');
    }

    public function test_seeder_budget_b2(): void
    {
        $this->seed(\Database\Seeders\BudgetB2Seeder::class);

        $this->assertGreaterThan(0, BudgetPreparation::where('budget_id', $this->budget->id)->count());
    }
}
