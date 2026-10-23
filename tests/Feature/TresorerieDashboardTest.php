<?php

namespace Tests\Feature;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Encaissement;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Recette;
use App\Models\User;
use App\Services\Navigation\MenuService;
use App\Services\Tresorerie\TresorerieService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2-5 — dashboard trésorerie unifié recettes / dépenses. */
class TresorerieDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['mfa.enabled' => false]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);

        $titre = BudgetTitre::create(['sens' => 'depense', 'code' => '1', 'intitule' => 'Personnel']);
        $art = BudgetArticle::create(['titre_id' => $titre->id, 'code' => '66', 'intitule' => 'C']);
        $par = BudgetParagraphe::create(['article_id' => $art->id, 'code' => '661', 'intitule' => 'T']);
        $rub = BudgetRubrique::create(['paragraphe_id' => $par->id, 'code' => '661000', 'intitule' => 'S']);

        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rub->id,
            'code' => 'LB-DEP',
            'montant_ouvert' => 10_000,
            'montant_engage' => 5_000,
            'montant_paye' => 2_000,
        ]);

        $titreR = BudgetTitre::create(['sens' => 'recette', 'code' => '7', 'intitule' => 'Recettes']);
        $artR = BudgetArticle::create(['titre_id' => $titreR->id, 'code' => '72', 'intitule' => 'Contrib']);
        $parR = BudgetParagraphe::create(['article_id' => $artR->id, 'code' => '721', 'intitule' => 'EM']);
        $rubR = BudgetRubrique::create(['paragraphe_id' => $parR->id, 'code' => '721010', 'intitule' => 'C']);

        $recette = Recette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'REC-TRES-1',
            'rubrique_id' => $rubR->id,
            'montant_prevu' => 8_000,
            'montant_realise' => 6_000,
            'statut' => 'realisee',
        ]);

        Encaissement::create([
            'recette_id' => $recette->id,
            'reference' => 'ENC-TRES-1',
            'montant' => 4_000,
            'mode_paiement' => 'virement',
            'date_encaissement' => '2026-03-15',
            'statut' => 'valide',
        ]);

        $engagement = Engagement::create([
            'exercice_id' => $this->exercice->id,
            'numero' => 'ENG-TRES-1',
            'objet' => 'Test trésorerie',
            'montant_total' => 1_500,
            'statut' => 'vise',
        ]);
        $liq = Liquidation::create(['numero' => 'LIQ-TRES-1', 'engagement_id' => $engagement->id, 'montant' => 1_500, 'statut' => 'valide']);
        $ord = Ordonnancement::create(['numero' => 'ORD-TRES-1', 'liquidation_id' => $liq->id, 'montant' => 1_500, 'statut' => 'valide']);

        Paiement::create([
            'numero' => 'PAY-TRES-1',
            'ordonnancement_id' => $ord->id,
            'montant' => 1_500,
            'date_paiement' => '2026-03-20',
            'mode_paiement' => 'virement',
            'statut' => 'valide',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_synthese_agrege_encaissements_et_paiements(): void
    {
        $s = app(TresorerieService::class)->synthese($this->exercice->id);

        $this->assertEquals(4_000, $s['flux']['encaissements']);
        $this->assertEquals(1_500, $s['flux']['decaissements']);
        $this->assertEquals(2_500, $s['flux']['net']);
        $this->assertEquals(6_000, $s['recettes']['realise']);
        $this->assertEquals(0, $s['recettes']['prevu']);
        $this->assertEquals(10_000, $s['depenses']['ouvert']);
        $this->assertEquals(2_000, $s['depenses']['paye']);
        $this->assertCount(1, $s['evolution_mensuelle']);
        $this->assertSame('2026-03', $s['evolution_mensuelle'][0]['mois']);
    }

    public function test_page_tresorerie_accessible(): void
    {
        $this->actingAs($this->user('secretaire_general'))
            ->get(route('tableaux.tresorerie', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Trésorerie unifiée')
            ->assertSee('Encaissements validés')
            ->assertSee('Flux net');
    }

    public function test_page_tresorerie_refuse_sans_droit(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->get(route('tableaux.tresorerie'))
            ->assertForbidden();
    }

    public function test_menu_contient_tresorerie_unifiee(): void
    {
        $labels = collect(app(MenuService::class)->build($this->user('administrateur')))->pluck('label');

        $this->assertTrue($labels->contains('Trésorerie unifiée'));
    }
}
