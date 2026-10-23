<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Engagement;
use App\Models\EngagementImputation;
use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\LigneBudgetaire;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Pap;
use App\Models\PapActiviteProgrammation;
use App\Models\PapHistorique;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProgrammationPluriannuelle;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\User;
use App\Services\Budget\ExerciceService;
use App\Services\Pap\PapExecutionFinanciereService;
use App\Services\Pap\PapIntegrationsService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase P4 — chaîne dépense, alignement programmation, clôture auto PAP. */
class PapP4Test extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->directeur = User::factory()->create(['actif' => true])->assignRole('directeur');
    }

    public function test_migration_pivot_activite_programmation(): void
    {
        $this->assertTrue(Schema::hasTable('pap_activite_programmations'));
    }

    public function test_chaine_depense_par_activite(): void
    {
        ['pap' => $pap, 'activite' => $activite, 'ligne' => $ligne] = $this->creerPapAvecActiviteEtLigne();

        $eng = Engagement::create([
            'numero' => 'ENG-P4-01',
            'exercice_id' => $pap->exercice_id,
            'type' => 'simple',
            'objet' => 'Test P4',
            'montant_total' => 500,
            'statut' => 'vise',
            'credits_reserves' => true,
        ]);
        EngagementImputation::create([
            'engagement_id' => $eng->id,
            'ligne_budgetaire_id' => $ligne->id,
            'montant' => 500,
        ]);
        $liq = Liquidation::create([
            'numero' => 'LIQ-P4-01',
            'engagement_id' => $eng->id,
            'montant' => 500,
            'statut' => 'valide',
        ]);
        $ord = Ordonnancement::create([
            'numero' => 'ORD-P4-01',
            'liquidation_id' => $liq->id,
            'montant' => 500,
            'statut' => 'valide',
        ]);
        Paiement::create([
            'numero' => 'PAY-P4-01',
            'ordonnancement_id' => $ord->id,
            'montant' => 500,
            'mode_paiement' => 'virement',
            'statut' => 'valide',
        ]);

        $chaine = app(PapExecutionFinanciereService::class)->chainePourExercice($pap->exercice_id);
        $noeud = $chaine['activites']->firstWhere('code', $activite->code);

        $this->assertNotNull($noeud);
        $this->assertSame(1, $noeud['nb_engagements']);
        $this->assertSame(500.0, $noeud['montant_paye']);
        $this->assertSame(50.0, $noeud['taux_execution']);
    }

    public function test_page_execution_financiere_accessible(): void
    {
        ['pap' => $pap] = $this->creerPapAvecActiviteEtLigne();

        $this->actingAs($this->directeur)
            ->get(route('paps.execution-financiere', $pap))
            ->assertOk()
            ->assertSee('Exécution financière du PAP')
            ->assertSee('Chaîne dépense par activité')
            ->assertSee('Alignement programmation stratégique');
    }

    public function test_synchroniser_alignements_activite_projet(): void
    {
        ['pap' => $pap, 'activite' => $activite] = $this->creerPapAvecActiviteEtLigne();
        $this->creerProgrammationAvecProjet($pap, $activite);

        $count = app(PapIntegrationsService::class)->synchroniserAlignements($pap->exercice_id);

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertDatabaseHas('pap_activite_programmations', [
            'activite_id' => $activite->id,
            'mode' => 'auto',
        ]);
    }

    public function test_cloture_exercice_cloture_pap(): void
    {
        $exercice = Exercice::create([
            'code' => '2030', 'annee' => 2030, 'libelle' => 'Ex 2030',
            'statut' => 'ouvert', 'date_debut' => '2030-01-01', 'date_fin' => '2030-12-31', 'devise' => 'XAF',
        ]);
        $pap = Pap::create([
            'exercice_id' => $exercice->id,
            'reference' => 'PAP-2030',
            'libelle' => 'PAP 2030',
            'statut' => 'publie',
            'version' => 1,
        ]);

        app(ExerciceService::class)->cloturer($exercice, $this->directeur);

        $this->assertSame('clos', $exercice->fresh()->statut);
        $this->assertSame('clos', $pap->fresh()->statut);
        $this->assertTrue(
            PapHistorique::where('pap_id', $pap->id)->where('action', 'cloture_exercice')->exists()
        );
    }

    public function test_route_synchroniser_alignements(): void
    {
        ['pap' => $pap, 'activite' => $activite] = $this->creerPapAvecActiviteEtLigne();
        $this->creerProgrammationAvecProjet($pap, $activite);

        $this->actingAs($this->directeur)
            ->post(route('paps.alignements.sync', $pap))
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertTrue(PapActiviteProgrammation::where('activite_id', $activite->id)->exists());
    }

    /** @return array{pap: Pap, activite: Activite, ligne: LigneBudgetaire} */
    private function creerPapAvecActiviteEtLigne(): array
    {
        $exercice = Exercice::create(['annee' => 2031, 'libelle' => 'Ex 2031', 'statut' => 'ouvert']);
        $pap = Pap::create([
            'exercice_id' => $exercice->id,
            'reference' => 'PAP-2031',
            'libelle' => 'PAP test P4',
            'statut' => 'brouillon',
            'version' => 1,
        ]);
        $pilier = Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P1', 'intitule' => 'Pilier', 'pap_id' => $pap->id]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);

        $titre = BudgetTitre::create(['sens' => 'depense', 'code' => '1', 'intitule' => 'P']);
        $art = BudgetArticle::create(['titre_id' => $titre->id, 'code' => '66', 'intitule' => 'C']);
        $par = BudgetParagraphe::create(['article_id' => $art->id, 'code' => '661', 'intitule' => 'T']);
        $rub = BudgetRubrique::create(['paragraphe_id' => $par->id, 'code' => '661000', 'intitule' => 'S']);
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rub->id,
            'code' => 'LB-P4',
            'montant_ouvert' => 1000,
        ]);

        $activite = Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $prod->id,
            'code' => 'ACT-P4',
            'intitule' => 'Activité P4',
            'montant_total' => 1000,
            'ligne_budgetaire_id' => $ligne->id,
        ]);
        $ligne->update(['activite_id' => $activite->id]);

        return ['pap' => $pap, 'activite' => $activite, 'ligne' => $ligne];
    }

    private function creerProgrammationAvecProjet(Pap $pap, Activite $activite): void
    {
        $plan = PlanStrategique::create([
            'reference' => 'PS-P4',
            'intitule' => 'Plan test P4',
            'statut' => 'actif',
            'date_debut' => '2030-01-01',
            'date_fin' => '2035-12-31',
        ]);
        $orientation = OrientationStrategique::create([
            'plan_strategique_id' => $plan->id,
            'code' => 'O1',
            'intitule' => 'Orientation P4',
            'statut' => 'actif',
        ]);
        $programme = ProgrammeStrategique::create([
            'orientation_strategique_id' => $orientation->id,
            'code' => 'PG-P4',
            'intitule' => 'Programme P4',
            'statut' => 'actif',
        ]);
        $projet = ProjetStrategique::create([
            'programme_strategique_id' => $programme->id,
            'code' => 'PJ-P4',
            'intitule' => 'Projet P4',
            'statut' => 'actif',
            'responsable_user_id' => $this->directeur->id,
        ]);
        $activite->update(['projet_id' => $projet->id]);

        $pluri = ProgrammationPluriannuelle::create([
            'plan_strategique_id' => $plan->id,
            'reference' => 'PL-P4',
            'annee_debut' => 2030,
            'annee_fin' => 2035,
            'statut' => 'actif',
        ]);
        ProgrammationAnnuelle::create([
            'programmation_pluriannuelle_id' => $pluri->id,
            'exercice_id' => $pap->exercice_id,
            'pap_id' => $pap->id,
            'statut' => 'actif',
        ]);
    }
}
