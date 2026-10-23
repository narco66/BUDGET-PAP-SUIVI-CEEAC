<?php

namespace Tests\Feature;

use App\Imports\PapImport;
use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** Phase E1 — liaison PAP↔piliers et cohérence engagement ↔ activité ↔ ligne. */
class RbmPhaseE1Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_synchroniser_liens_piliers_apres_publication_pap(): void
    {
        $pap = Pap::create(['exercice_id' => $this->exercice->id, 'libelle' => 'PAP', 'statut' => 'en_validation']);
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $this->assertNull($pilier->fresh()->pap_id);

        $this->actingAs($this->user('directeur'));
        $pap->auWorkflowValide();

        $this->assertSame('publie', $pap->fresh()->statut);
        $this->assertSame($pap->id, $pilier->fresh()->pap_id);
    }

    public function test_import_pap_lie_tous_piliers_au_pap(): void
    {
        $pap = Pap::create(['exercice_id' => $this->exercice->id, 'libelle' => 'PAP', 'statut' => 'brouillon']);
        Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-ORPHELIN', 'intitule' => 'Orphelin']);

        $path = storage_path('app/test_pap_e1.csv');
        file_put_contents($path,
            "pilier_code,pilier_intitule,axe_code,axe_intitule,produit_code,produit_intitule,sous_produit_code,sous_produit_intitule,activite_code,activite_intitule,montant_ceeac,montant_ptf\n".
            "P1,Pilier,A1,Axe,PR1,Prod,SP1,SP,ACT1,Act,1000,0\n"
        );
        Excel::import(new PapImport($this->exercice->id), $path);
        @unlink($path);

        $piliers = Pilier::where('exercice_id', $this->exercice->id)->get();
        $this->assertGreaterThanOrEqual(2, $piliers->count());
        foreach ($piliers as $pilier) {
            $this->assertSame($pap->id, $pilier->pap_id, "Pilier {$pilier->code} non rattaché au PAP.");
        }
    }

    public function test_engagement_coherent_activite_ligne(): void
    {
        $rubrique = \App\Models\BudgetRubrique::first();
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT1', 'intitule' => 'Act', 'montant_total' => 1000,
        ]);
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-COH',
            'montant_ouvert' => 500000,
            'activite_id' => $activite->id,
        ]);
        $activite->update(['ligne_budgetaire_id' => $ligne->id]);

        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-E1',
            'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id,
            'demandeur_id' => $this->user('gestionnaire_budgetaire')->id,
            'objet' => 'Besoin cohérent',
            'montant_estime' => 10000,
            'statut' => 'valide',
        ]);

        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->post(route('engagements.store'), [
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'type' => 'simple',
                'objet' => 'Engagement cohérent',
                'imputations' => [
                    ['ligne_budgetaire_id' => $ligne->id, 'montant' => 10000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('succes');
    }

    public function test_engagement_rejette_imputation_incoherente_activite(): void
    {
        $rubrique = \App\Models\BudgetRubrique::first();
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT1', 'intitule' => 'Act', 'montant_total' => 1000,
        ]);
        $ligneBesoin = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-BESOIN',
            'montant_ouvert' => 500000,
            'activite_id' => $activite->id,
        ]);
        $activite->update(['ligne_budgetaire_id' => $ligneBesoin->id]);

        $autreActivite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT2', 'intitule' => 'Autre', 'montant_total' => 500,
        ]);
        $ligneHors = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-HORS',
            'montant_ouvert' => 200000,
            'activite_id' => $autreActivite->id,
        ]);

        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-INC',
            'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id,
            'demandeur_id' => $this->user('gestionnaire_budgetaire')->id,
            'objet' => 'Besoin',
            'montant_estime' => 5000,
            'statut' => 'valide',
        ]);

        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->post(route('engagements.store'), [
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'type' => 'simple',
                'objet' => 'Engagement incohérent',
                'imputations' => [
                    ['ligne_budgetaire_id' => $ligneHors->id, 'montant' => 5000],
                ],
            ])
            ->assertSessionHasErrors('imputations.0.ligne_budgetaire_id');
    }
}
