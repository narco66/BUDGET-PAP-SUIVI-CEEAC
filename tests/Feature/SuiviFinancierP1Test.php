<?php

namespace Tests\Feature;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\SituationBudgetaire;
use App\Models\User;
use App\Services\Budget\SituationBudgetaireService;
use App\Services\TableauBordService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P1 — restes, snapshots et exports suivi financier. */
class SuiviFinancierP1Test extends TestCase
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
            'code' => 'LB-RAE',
            'montant_ouvert' => 1000,
            'montant_revise' => 1200,
            'montant_engage' => 400,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_situation_budgetaire_persistee(): void
    {
        $svc = app(SituationBudgetaireService::class);
        $live = app(TableauBordService::class)->executionBudgetaire($this->exercice->id);
        $snap = $svc->capturer($this->exercice->id, $this->admin()->id);

        $this->assertEquals($live['alloue'], (float) $snap->budget_revise);
        $this->assertEquals($live['reste_a_engager'], (float) $snap->reste_a_engager);
        $this->assertEquals($live['taux_engagement'], (float) $snap->taux_engagement);
    }

    public function test_capture_idempotente_meme_jour(): void
    {
        $svc = app(SituationBudgetaireService::class);
        $user = $this->admin();

        $a = $svc->capturer($this->exercice->id, $user->id);
        $b = $svc->capturer($this->exercice->id, $user->id);

        $this->assertSame($a->id, $b->id);
        $this->assertEquals(1, SituationBudgetaire::where('exercice_id', $this->exercice->id)->count());
    }

    public function test_reste_a_engager_export(): void
    {
        $this->actingAs($this->admin())
            ->get(route('depense.export.restes-engager', ['exercice' => 2026]))
            ->assertOk();

        $lignes = app(SituationBudgetaireService::class)->restesAEngager($this->exercice->id);
        $this->assertEquals(800, $lignes->first()['reste_a_engager']);
    }

    public function test_dashboard_affiche_restes(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Restes à engager')
            ->assertSee('Restes à payer');
    }

    public function test_rapport_situation_budgetaire(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reporting.show', [
                'type' => 'situation_budgetaire',
                'exercice' => $this->exercice->id,
            ]))
            ->assertOk()
            ->assertSee('Restes à engager');
    }
}
