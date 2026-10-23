<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Structure;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use App\Services\Pap\PapAuditService;
use App\Services\Pap\PapPerformanceService;
use App\Services\Pap\PapReferentielService;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PapModuleTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private Activite $activite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $this->activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT1', 'intitule' => 'A', 'montant_total' => 1000,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_taux_financier_reel_depuis_ligne_budgetaire(): void
    {
        $rubrique = \App\Models\BudgetRubrique::first();
        $lb = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB1',
            'intitule' => 'Ligne',
            'montant_ouvert' => 1000,
            'montant_paye' => 250,
            'activite_id' => $this->activite->id,
        ]);
        $this->activite->update(['ligne_budgetaire_id' => $lb->id]);

        $svc = app(PapPerformanceService::class);
        $this->assertEquals(25.0, $svc->tauxFinancierReel($this->activite->fresh('ligneBudgetaire')));
    }

    public function test_execution_pap_utilise_montant_paye(): void
    {
        $rubrique = \App\Models\BudgetRubrique::first();
        $lb = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB1',
            'intitule' => 'Ligne',
            'montant_ouvert' => 1000,
            'montant_paye' => 500,
            'activite_id' => $this->activite->id,
        ]);
        $this->activite->update(['ligne_budgetaire_id' => $lb->id]);

        $exec = app(PapPerformanceService::class)->executionPap($this->exercice->id);
        $this->assertEquals(50.0, $exec['taux_financier']);
        $this->assertEquals(500.0, $exec['montant_paye']);
    }

    public function test_taux_financier_pondere_par_montant(): void
    {
        $rubrique = \App\Models\BudgetRubrique::first();
        $sp = $this->activite->sous_produit_id;
        $prod = $this->activite->produit_id;

        $act2 = Activite::create([
            'sous_produit_id' => $sp, 'produit_id' => $prod,
            'code' => 'ACT2', 'intitule' => 'B', 'montant_total' => 3000,
        ]);

        $lb1 = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id, 'rubrique_id' => $rubrique->id,
            'code' => 'LB1', 'montant_ouvert' => 1000, 'montant_paye' => 500,
            'activite_id' => $this->activite->id,
        ]);
        $lb2 = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id, 'rubrique_id' => $rubrique->id,
            'code' => 'LB2', 'montant_ouvert' => 3000, 'montant_paye' => 0,
            'activite_id' => $act2->id,
        ]);
        $this->activite->update(['ligne_budgetaire_id' => $lb1->id]);
        $act2->update(['ligne_budgetaire_id' => $lb2->id]);

        $exec = app(PapPerformanceService::class)->executionPap($this->exercice->id);
        // (500 + 0) / (1000 + 3000) = 12.5%
        $this->assertEquals(12.5, $exec['taux_financier']);
        $this->assertEquals(25.0, $exec['taux_financier_moyen']);
    }

    public function test_synchroniser_taux_financier_persiste_sur_activite(): void
    {
        $rubrique = \App\Models\BudgetRubrique::first();
        $lb = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id, 'rubrique_id' => $rubrique->id,
            'code' => 'LB-SYNC', 'montant_ouvert' => 2000, 'montant_paye' => 400,
            'activite_id' => $this->activite->id,
        ]);
        $this->activite->update(['ligne_budgetaire_id' => $lb->id, 'montant_total' => 2000, 'taux_realisation_financier' => 0]);

        app(PapPerformanceService::class)->synchroniserTauxFinancier($this->activite->fresh());
        $this->assertEquals(20.0, (float) $this->activite->fresh()->taux_realisation_financier);
    }

    public function test_commande_sync_taux_financiers(): void
    {
        $this->artisan('pap:sync-taux-financiers', ['--exercice' => $this->exercice->id])
            ->assertSuccessful();
    }

    public function test_audit_detecte_absence_activite_critique(): void
    {
        Activite::query()->delete();
        $audit = app(PapAuditService::class)->executer($this->exercice->id);
        $this->assertGreaterThan(0, $audit['anomalies_critiques']);
    }

    public function test_verrou_rbm_si_pap_publie(): void
    {
        Pap::create(['exercice_id' => $this->exercice->id, 'libelle' => 'PAP', 'statut' => 'publie']);

        $this->assertTrue(app(PapReferentielService::class)->exerciceVerrouille($this->exercice->id));
        $this->expectException(\RuntimeException::class);
        app(PapReferentielService::class)->assertExerciceModifiable($this->exercice->id);
    }

    public function test_soumission_cree_historique(): void
    {
        $pap = Pap::updateOrCreate(
            ['exercice_id' => $this->exercice->id],
            ['libelle' => 'PAP 2026', 'statut' => 'brouillon']
        );

        $this->actingAs($this->user('directeur'))
            ->post(route('paps.soumettre', $pap))
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertDatabaseHas('pap_historiques', [
            'pap_id' => $pap->id,
            'action' => 'soumission',
        ]);
    }

    public function test_commande_pap_audit(): void
    {
        $this->artisan('pap:audit', ['--exercice' => $this->exercice->id])
            ->assertSuccessful();
    }

    public function test_import_lie_ligne_budgetaire_par_code(): void
    {
        $rubrique = \App\Models\BudgetRubrique::first();
        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'ACT1',
            'montant_ouvert' => 1000,
            'montant_paye' => 200,
        ]);

        $path = storage_path('app/test_pap_liaison.csv');
        file_put_contents($path,
            "pilier_code,pilier_intitule,axe_code,axe_intitule,produit_code,produit_intitule,sous_produit_code,sous_produit_intitule,activite_code,activite_intitule,montant_ceeac,montant_ptf\n".
            "P1,P,A1,A,PR1,PR,SP1,SP,ACT1,Act,1000,0\n"
        );
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\PapImport($this->exercice->id), $path);
        @unlink($path);

        $act = $this->activite->fresh();
        $this->assertNotNull($act->ligne_budgetaire_id);
        $this->assertEquals(200.0, (float) LigneBudgetaire::find($act->ligne_budgetaire_id)->montant_paye);
    }

    public function test_revision_ouvre_et_deverrouille_rbm(): void
    {
        $pap = Pap::updateOrCreate(
            ['exercice_id' => $this->exercice->id],
            ['libelle' => 'PAP', 'statut' => 'publie', 'publie_le' => now()]
        );

        $response = $this->actingAs($this->user('directeur'))
            ->post(route('paps.revision', $pap), ['motif' => 'Révision budgétaire T2']);

        if ($response->status() !== 302 || ! session()->has('succes')) {
            dump($response->status(), session()->all());
        }

        $response->assertRedirect()->assertSessionHas('succes');

        $this->assertSame('en_revision', $pap->fresh()->statut);
        $this->assertFalse(app(PapReferentielService::class)->exerciceVerrouille($this->exercice->id));
        $this->assertDatabaseHas('pap_historiques', ['pap_id' => $pap->id, 'action' => 'ouverture_revision']);
    }

    public function test_classement_departements(): void
    {
        $dep = Structure::create(['code' => 'DEP1', 'libelle' => 'Département Test', 'type' => 'departement']);
        $this->activite->update(['structure_id' => $dep->id, 'taux_realisation_physique' => 80]);

        $classement = app(PapPerformanceService::class)->classementDepartements($this->exercice->id);
        $this->assertCount(1, $classement);
        $this->assertSame(1, $classement->first()['rang']);
        $this->assertSame('Département Test', $classement->first()['libelle']);
    }

    public function test_pilier_lie_au_pap(): void
    {
        $pap = Pap::updateOrCreate(
            ['exercice_id' => $this->exercice->id],
            ['libelle' => 'PAP', 'statut' => 'brouillon']
        );
        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();
        app(PapReferentielService::class)->lierPilier($pilier, $pap);
        $this->assertSame($pap->id, $pilier->fresh()->pap_id);
    }
}
