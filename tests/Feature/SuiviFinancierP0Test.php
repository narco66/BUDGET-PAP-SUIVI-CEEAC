<?php

namespace Tests\Feature;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Services\Budget\SuiviFinancierCalculator;
use App\Services\Reporting\ReportingService;
use App\Services\TableauBordService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P0 — calculs unifiés et 6 taux de suivi financier. */
class SuiviFinancierP0Test extends TestCase
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

        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rub->id,
            'code' => 'LB-P0',
            'montant_ouvert' => 1000,
            'montant_revise' => 1200,
            'montant_engage' => 800,
            'montant_liquide' => 600,
            'montant_ordonnance' => 400,
            'montant_paye' => 200,
        ]);
    }

    public function test_calculator_six_taux_et_restes(): void
    {
        $s = SuiviFinancierCalculator::synthese(1000, 800, 600, 400, 200, 1200);

        $this->assertEquals(1200, $s['alloue']);
        $this->assertEquals(400, $s['disponible']);
        $this->assertEquals(400, $s['reste_a_engager']);
        $this->assertEquals(200, $s['reste_a_payer']);
        $this->assertEquals(66.7, $s['taux_engagement']);
        $this->assertEquals(75.0, $s['taux_liquidation']);
        $this->assertEquals(66.7, $s['taux_ordonnancement']);
        $this->assertEquals(50.0, $s['taux_paiement']);
        $this->assertEqualsWithDelta(16.7, $s['taux_execution'], 0.1);
    }

    public function test_tableau_bord_et_reporting_alignes(): void
    {
        $tb = app(TableauBordService::class)->executionBudgetaire($this->exercice->id);
        $rp = app(ReportingService::class)->global($this->exercice->id);

        $this->assertEquals($tb['alloue'], $rp['alloue']);
        $this->assertEquals($tb['taux_engagement'], $rp['taux_engagement']);
        $this->assertEquals($tb['taux_liquidation'], $rp['taux_liquidation']);
        $this->assertEquals($tb['taux_execution'], $rp['taux_execution']);
        $this->assertEquals(1200, $tb['alloue']);
    }

    public function test_taux_engagement_sur_budget_revise(): void
    {
        $b = app(TableauBordService::class)->executionBudgetaire($this->exercice->id);

        $this->assertEquals(66.7, $b['taux_engagement']);
        $this->assertNotEquals(round(800 / 1000 * 100, 1), $b['taux_engagement']);
    }
}
