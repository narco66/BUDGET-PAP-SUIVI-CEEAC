<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Models\Pilier;
use App\Models\SousProduit;
use App\Models\User;
use App\Services\Budget\BudgetAuditService;
use App\Services\Budget\RbmBudgetSyncService;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tests audit et renforcement intégration nomenclature budgétaire. */
class BudgetNomenclatureAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);
    }

    public function test_sync_rbm_budget_bidirectionnel(): void
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $rubrique = \App\Models\BudgetRubrique::first();
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-TEST',
            'montant_ouvert' => 1000000,
            'montant_revise' => 1000000,
        ]);

        $pilier = Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P1', 'intitule' => 'Pilier', 'statut' => 'actif']);
        $axe = $pilier->axes()->create(['code' => 'A1', 'intitule' => 'Axe', 'statut' => 'actif']);
        $produit = $axe->produits()->create(['code' => 'PR1', 'intitule' => 'Produit', 'statut' => 'actif']);
        $sp = $produit->sousProduits()->create(['code' => 'SP1', 'intitule' => 'SP', 'statut' => 'actif']);
        $activite = $sp->activites()->create([
            'code' => 'ACT1',
            'intitule' => 'Activité test',
            'ligne_budgetaire_id' => $ligne->id,
            'montant_ceeac' => 500000,
            'montant_ptf' => 0,
            'statut' => 'actif',
        ]);

        app(RbmBudgetSyncService::class)->synchroniserActivite($activite);

        $this->assertEquals($activite->id, $ligne->fresh()->activite_id);
    }

    public function test_besoin_herite_ligne_de_activite(): void
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $rubrique = \App\Models\BudgetRubrique::first();
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-BESOIN',
            'montant_ouvert' => 500000,
        ]);

        $pilier = Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P2', 'intitule' => 'P', 'statut' => 'actif']);
        $axe = $pilier->axes()->create(['code' => 'A2', 'intitule' => 'A', 'statut' => 'actif']);
        $produit = $axe->produits()->create(['code' => 'PR2', 'intitule' => 'P', 'statut' => 'actif']);
        $sp = $produit->sousProduits()->create(['code' => 'SP2', 'intitule' => 'SP', 'statut' => 'actif']);
        $activite = $sp->activites()->create([
            'code' => 'ACT2',
            'intitule' => 'Act',
            'ligne_budgetaire_id' => $ligne->id,
            'statut' => 'actif',
        ]);

        $user = User::factory()->create(['actif' => true]);
        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-TEST',
            'exercice_id' => $exercice->id,
            'activite_id' => $activite->id,
            'demandeur_id' => $user->id,
            'objet' => 'Test',
            'montant_estime' => 100000,
            'statut' => 'brouillon',
        ]);

        $this->assertEquals($ligne->id, $besoin->fresh()->ligne_budgetaire_id);
    }

    public function test_engagement_refuse_imputation_hors_exercice(): void
    {
        $ex2026 = Exercice::create(['annee' => 2026, 'libelle' => '2026', 'statut' => 'ouvert']);
        $ex2025 = Exercice::create(['annee' => 2025, 'libelle' => '2025', 'statut' => 'clos']);
        $rubrique = \App\Models\BudgetRubrique::first();

        $ligne2025 = LigneBudgetaire::create([
            'exercice_id' => $ex2025->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-2025',
            'montant_ouvert' => 1000000,
        ]);

        $admin = User::factory()->create(['actif' => true])->assignRole('administrateur');

        $this->actingAs($admin)
            ->post(route('engagements.store'), [
                'exercice_id' => $ex2026->id,
                'type' => 'simple',
                'objet' => 'Test hors exercice',
                'imputations' => [
                    ['ligne_budgetaire_id' => $ligne2025->id, 'montant' => 10000],
                ],
            ])
            ->assertSessionHasErrors('imputations.0.ligne_budgetaire_id');
    }

    public function test_commande_audit_budget(): void
    {
        $this->artisan('budget:audit')->assertSuccessful();
    }

    public function test_disponible_utilise_montant_revise(): void
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => \App\Models\BudgetRubrique::first()->id,
            'code' => 'LB-REV',
            'montant_ouvert' => 1000000,
            'montant_revise' => 800000,
            'montant_engage' => 200000,
        ]);

        $this->assertEquals(600000.0, $ligne->disponible);
    }

    public function test_commande_reconcilier(): void
    {
        $this->artisan('budget:reconcilier')->assertSuccessful();
    }
}
