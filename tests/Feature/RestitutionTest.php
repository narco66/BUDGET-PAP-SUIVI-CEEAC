<?php

namespace Tests\Feature;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Services\TableauBordService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestitutionTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);

        $titre = BudgetTitre::create(['sens' => 'depense', 'code' => '1', 'intitule' => 'Personnel']);
        $art = BudgetArticle::create(['titre_id' => $titre->id, 'code' => '66', 'intitule' => 'C']);
        $par = BudgetParagraphe::create(['article_id' => $art->id, 'code' => '661', 'intitule' => 'T']);
        $rub = BudgetRubrique::create(['paragraphe_id' => $par->id, 'code' => '661000', 'intitule' => 'S']);

        LigneBudgetaire::create(['exercice_id' => $this->exercice->id, 'rubrique_id' => $rub->id, 'code' => 'A', 'montant_ouvert' => 1000, 'montant_engage' => 300, 'montant_paye' => 100]);
        LigneBudgetaire::create(['exercice_id' => $this->exercice->id, 'rubrique_id' => $rub->id, 'code' => 'B', 'montant_ouvert' => 500]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_agregation_execution_budgetaire(): void
    {
        $b = app(TableauBordService::class)->executionBudgetaire($this->exercice->id);

        $this->assertEquals(1500, $b['ouvert']);
        $this->assertEquals(300, $b['engage']);
        $this->assertEquals(100, $b['paye']);
        $this->assertEquals(1200, $b['disponible']);
        $this->assertEquals(20.0, $b['taux_engagement']);
        $this->assertEqualsWithDelta(6.7, $b['taux_execution'], 0.1);
    }

    public function test_tableau_de_bord_accessible(): void
    {
        $this->actingAs($this->user('secretaire_general'))
            ->get(route('tableaux.index', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('exécution');
    }

    public function test_tableau_de_bord_refuse_sans_droit(): void
    {
        // point_focal n'a pas tableau_bord.voir
        $this->actingAs($this->user('point_focal'))
            ->get(route('tableaux.index'))
            ->assertForbidden();
    }

    public function test_rapport_execution_pdf(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->get(route('rapports.execution.pdf', ['exercice' => $this->exercice->id]))
            ->assertRedirect(route('reporting.generer', [
                'type' => 'execution_synthese',
                'exercice' => $this->exercice->id,
                'format' => 'pdf',
            ], false));
    }

    public function test_rapport_execution_pdf_via_reporting(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->get(route('reporting.generer', [
                'type' => 'execution_synthese',
                'exercice' => $this->exercice->id,
                'format' => 'pdf',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_rapport_execution_excel(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->get(route('rapports.execution.excel', ['exercice' => $this->exercice->id]))
            ->assertRedirect();
    }

    public function test_rapport_execution_excel_via_reporting(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->get(route('reporting.generer', [
                'type' => 'execution_synthese',
                'exercice' => $this->exercice->id,
                'format' => 'excel',
            ]))
            ->assertOk();
    }

    public function test_rapport_pap_pdf(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->get(route('rapports.pap.pdf', ['exercice' => $this->exercice->id]))
            ->assertRedirect(route('reporting.generer', [
                'type' => 'pap',
                'exercice' => $this->exercice->id,
                'format' => 'pdf',
            ], false));
    }

    public function test_rapport_pap_pdf_via_reporting(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->get(route('reporting.generer', [
                'type' => 'pap',
                'exercice' => $this->exercice->id,
                'format' => 'pdf',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_refuse_sans_droit_exporter(): void
    {
        // gestionnaire_budgetaire : a rapport.voir (*.voir) mais pas rapport.exporter
        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->get(route('rapports.index'))
            ->assertRedirect(route('reporting.index', ['exercice' => $this->exercice->id], false));

        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->get(route('rapports.execution.pdf', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }
}
