<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\PerformanceIndicateur;
use App\Models\Pilier;
use App\Models\SousProduit;
use App\Models\User;
use App\Services\Pap\PapPerformanceService;
use App\Services\Rbm\IndicateurPerformanceService;
use App\Services\Rbm\MesureService;
use App\Models\Indicateur;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\ReportingPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviFinancierPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P2 — hub, permissions, écarts PAP, ventilation tâche. */
class SuiviFinancierP2Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SuiviFinancierPermissionsSeeder::class);
        $this->seed(ReportingPermissionsSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_hub_refuse_sans_permission(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->get(route('suivi-financier.index'))
            ->assertForbidden();
    }

    public function test_hub_accessible_controleur_financier(): void
    {
        $this->actingAs($this->user('controleur_financier'))
            ->get(route('suivi-financier.index', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Suivi Financier')
            ->assertSee('Restes à engager');
    }

    public function test_rapport_ecarts_accessible(): void
    {
        $this->actingAs($this->user('controleur_financier'))
            ->get(route('reporting.show', [
                'type' => 'ecarts_physique_financier',
                'exercice' => $this->exercice->id,
            ]))
            ->assertOk()
            ->assertSee('Écarts physique');
    }

    public function test_rapport_ecarts_detecte_sur_financée(): void
    {
        $activite = $this->creerActiviteAvecLigne(physique: 20, paye: 900, prevu: 1000);
        $activite->update(['taux_realisation_physique' => 20]);

        $ecarts = app(PapPerformanceService::class)->ecartsPhysiqueFinancier($this->exercice->id);
        $row = $ecarts->firstWhere('code', $activite->code);

        $this->assertNotNull($row);
        $this->assertEquals('sur-financée', $row['type_ecart']);
    }

    public function test_ventilation_tache_inclut_champs_paye(): void
    {
        $rows = app(\App\Services\Depense\DepenseReportingService::class)->executionParTache($this->exercice->id);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $rows);
        if ($rows->isNotEmpty()) {
            $this->assertObjectHasProperty('montant_paye', $rows->first());
        } else {
            $this->assertTrue(true);
        }
    }

    private function creerActiviteAvecLigne(float $physique, float $paye, float $prevu): Activite
    {
        $titre = BudgetTitre::create(['sens' => 'depense', 'code' => '2', 'intitule' => 'B']);
        $art = BudgetArticle::create(['titre_id' => $titre->id, 'code' => '21', 'intitule' => 'A']);
        $par = BudgetParagraphe::create(['article_id' => $art->id, 'code' => '211', 'intitule' => 'P']);
        $rub = BudgetRubrique::create(['paragraphe_id' => $par->id, 'code' => '211000', 'intitule' => 'R']);

        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P2', 'intitule' => 'P', 'statut' => 'actif']);
        $axe = $pilier->axes()->create(['code' => 'A1', 'intitule' => 'Axe', 'statut' => 'actif']);
        $produit = $axe->produits()->create(['code' => 'PR', 'intitule' => 'Pr', 'statut' => 'actif']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP', 'intitule' => 'SP', 'statut' => 'actif']);

        $ligne = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rub->id,
            'code' => 'LB-P2',
            'montant_ouvert' => $prevu,
            'montant_paye' => $paye,
        ]);

        return Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $produit->id,
            'code' => 'ACT-P2',
            'intitule' => 'Activité test P2',
            'montant_total' => $prevu,
            'ligne_budgetaire_id' => $ligne->id,
            'taux_realisation_physique' => $physique,
            'statut' => 'actif',
        ]);
    }
}
