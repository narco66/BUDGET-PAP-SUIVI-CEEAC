<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use App\Services\Budget\StructureBudgetRecetteService;
use App\Services\Budget\StructureBudgetService;
use Database\Seeders\Budget2026Seeder;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SourceFinancementSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot 7 — Recette automatisée Budget 2026 (Structure du Budget). */
class StructureBudgetRecette2026Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            StructureSeeder::class,
            ExerciceSeeder::class,
            SourceFinancementSeeder::class,
            NomenclatureBudgetaireSeeder::class,
            Budget2026Seeder::class,
        ]);
        $this->exercice = Exercice::firstWhere('annee', 2026);
    }

    public function test_recette_automatique_2026_conforme(): void
    {
        $resultat = app(StructureBudgetRecetteService::class)->executer($this->exercice->id);

        $this->assertTrue($resultat['ok'], json_encode($resultat['controles'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(7, $resultat['score']);
        $this->assertSame(7, $resultat['total']);
    }

    public function test_commande_artisan_recette_2026(): void
    {
        $this->artisan('structure-budget:recette-2026')
            ->assertSuccessful()
            ->expectsOutputToContain('Recette CONFORME');
    }

    public function test_scenarios_moa_ui_budget_2026(): void
    {
        $admin = User::create([
            'name' => 'Admin Recette SB', 'email' => 'recette.sb@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $admin->assignRole('administrateur');

        $synthese = app(StructureBudgetService::class)->synthese($this->exercice->id);
        $this->assertSame(StructureBudgetRecetteService::TOTAL_OUVERT_2026, (int) $synthese['ouvert']);
        $this->assertGreaterThanOrEqual(StructureBudgetRecetteService::NB_LIGNES_2026, $synthese['nb_lignes']);

        $this->actingAs($admin)->get(route('structure-budget.arbre', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('Structure du budget');

        $this->actingAs($admin)->get(route('structure-budget.tableau', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('Tableau de bord');

        $this->actingAs($admin)->get(route('structure-budget.recherche', ['exercice' => $this->exercice->id]))
            ->assertOk();

        $this->actingAs($admin)->get(route('structure-budget.export', [
            'exercice' => $this->exercice->id, 'format' => 'xlsx',
        ]))->assertOk();
    }
}
