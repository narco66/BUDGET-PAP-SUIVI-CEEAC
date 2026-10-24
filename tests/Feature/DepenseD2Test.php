<?php



namespace Tests\Feature;



use App\Models\Engagement;

use App\Models\Exercice;

use App\Models\ExpressionBesoin;

use App\Models\Liquidation;

use App\Models\LiquidationPiece;

use App\Models\OrdrePaiement;

use App\Models\Ordonnancement;

use App\Models\Paiement;

use App\Models\ServiceFait;

use App\Models\User;

use App\Services\Depense\DepenseDossierService;

use App\Services\Depense\DepenseReportingService;

use App\Services\Depense\OrdrePaiementService;

use App\Services\Depense\ServiceFaitService;

use App\Services\EngagementService;

use App\Services\WorkflowService;

use Database\Seeders\DepensePermissionsSeeder;

use Database\Seeders\RoleSeeder;

use Database\Seeders\WorkflowSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesDepenseChain;

use Tests\TestCase;



/** Phase D2 — tâche, priorité, service fait, ordres paiement, dossier unifié. */

class DepenseD2Test extends TestCase

{

    use RefreshDatabase, CreatesDepenseChain;



    private Exercice $exercice;



    private WorkflowService $workflow;



    protected function setUp(): void

    {

        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->seed(DepensePermissionsSeeder::class);

        $this->seed(WorkflowSeeder::class);

        $this->workflow = app(WorkflowService::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

    }



    private function user(string $role): User

    {

        return User::factory()->create(['actif' => true])->assignRole($role);

    }



    public function test_besoin_avec_tache_et_priorite(): void

    {

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D2-BES', 5000);

        $tache = $this->tachePourActivite($activite, 'T-D2');

        $user = $this->user('chef_service');



        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 1200, 'EB-D2-01', $tache, 'haute');



        $this->assertSame($tache->id, $besoin->tache_id);

        $this->assertSame('haute', $besoin->priorite);

    }



    public function test_engagement_herite_tache_du_besoin(): void

    {

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D2-ENG', 5000);

        $tache = $this->tachePourActivite($activite, 'T-ENG');

        $user = $this->user('gestionnaire_budgetaire');

        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 900, 'EB-D2-ENG', $tache);



        $engagement = app(EngagementService::class)->creer(

            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Test tâche'],

            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 900]],

            $user,

        );



        $this->assertSame($tache->id, $engagement->tache_id);

    }



    public function test_service_fait_et_piece_liquidation(): void

    {

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D2-SF', 5000);

        $user = $this->user('gestionnaire_budgetaire');

        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 600, 'EB-D2-SF');

        $engagement = app(EngagementService::class)->creer(

            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'SF test'],

            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 600]],

            $user,

        );

        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);



        $liquidation = Liquidation::create([

            'numero' => 'LIQ-D2-SF',

            'engagement_id' => $engagement->id,

            'montant' => 600,

            'date_liquidation' => now(),

            'statut' => 'brouillon',

            'cree_par' => $user->id,

        ]);



        app(ServiceFaitService::class)->enregistrer($liquidation, [

            'service_fait_type' => 'reception',

            'service_fait_reference' => 'PV-001',

            'service_fait' => 'Prestation conforme au marché',

            'piece_type' => 'facture',

            'piece_reference' => 'FAC-2026-01',

        ], $user);



        $this->assertDatabaseHas('services_faits', ['liquidation_id' => $liquidation->id, 'type' => 'reception']);

        $this->assertDatabaseHas('liquidation_pieces', ['liquidation_id' => $liquidation->id, 'type' => 'facture']);

        $this->assertSame('Prestation conforme au marché', $liquidation->fresh()->service_fait);

    }



    public function test_ordre_paiement_emis_a_la_validation_ordonnancement(): void

    {

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D2-OP', 5000);

        $user = $this->user('gestionnaire_budgetaire');

        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 400, 'EB-D2-OP');

        $engagement = app(EngagementService::class)->creer(

            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'OP test'],

            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 400]],

            $user,

        );

        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);



        $liquidation = Liquidation::create([

            'numero' => 'LIQ-D2-OP', 'engagement_id' => $engagement->id, 'montant' => 400,

            'date_liquidation' => now(), 'statut' => 'valide', 'cree_par' => $user->id, 'compteurs_appliques' => true,

        ]);



        $ordonnancement = Ordonnancement::create([

            'numero' => 'ORD-D2-OP', 'liquidation_id' => $liquidation->id, 'montant' => 400,

            'date_ordonnancement' => now(), 'statut' => 'brouillon', 'cree_par' => $user->id,

        ]);



        $this->workflow->demarrer($ordonnancement, 'ORDONNANCEMENT', $user);

        $instance = $ordonnancement->fresh()->workflowActif;

        $this->workflow->valider($instance, $this->user('secretaire_general'));



        $ordre = OrdrePaiement::where('ordonnancement_id', $ordonnancement->id)->first();

        $this->assertNotNull($ordre);

        $this->assertSame('emis', $ordre->statut);

        $this->assertSame(400.0, (float) $ordre->montant);

    }



    public function test_paiement_lie_ordre_et_marque_execute(): void

    {

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D2-PAY', 5000);

        $user = $this->user('gestionnaire_budgetaire');

        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 300, 'EB-D2-PAY');

        $engagement = app(EngagementService::class)->creer(

            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Pay test'],

            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 300]],

            $user,

        );

        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);



        $liquidation = Liquidation::create([

            'numero' => 'LIQ-D2-PAY', 'engagement_id' => $engagement->id, 'montant' => 300,

            'date_liquidation' => now(), 'statut' => 'valide', 'cree_par' => $user->id, 'compteurs_appliques' => true,

        ]);



        $ordonnancement = Ordonnancement::create([

            'numero' => 'ORD-D2-PAY', 'liquidation_id' => $liquidation->id, 'montant' => 300,

            'date_ordonnancement' => now(), 'statut' => 'valide', 'cree_par' => $user->id, 'compteurs_appliques' => true,

        ]);



        $ordre = app(OrdrePaiementService::class)->emettre($ordonnancement, $user);



        $paiement = Paiement::create([

            'numero' => 'PAY-D2-01', 'ordonnancement_id' => $ordonnancement->id,

            'ordre_paiement_id' => $ordre->id, 'montant' => 300, 'date_paiement' => now(),

            'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $user->id,

        ]);



        $this->viserPaiement($paiement);



        $this->assertSame('valide', $paiement->fresh()->statut);

        $this->assertSame('execute', $ordre->fresh()->statut);

    }



    public function test_dossier_unifie_timeline_complete(): void

    {

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D2-DOS', 5000);

        $tache = $this->tachePourActivite($activite, 'T-DOS');

        $user = $this->user('gestionnaire_budgetaire');

        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 500, 'EB-D2-DOS', $tache, 'basse');



        $engagement = app(EngagementService::class)->creer(

            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Dossier test'],

            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 500]],

            $user,

        );

        $engagement->update(['statut' => 'vise']);



        $dossier = app(DepenseDossierService::class)->depuisEngagement($engagement);



        $this->assertSame('basse', $dossier['priorite']);

        $this->assertSame($tache->id, $dossier['tache']->id);

        $types = $dossier['timeline']->pluck('type')->all();

        $this->assertContains('besoin', $types);

        $this->assertContains('engagement', $types);

    }



    public function test_reporting_ventile_par_tache(): void

    {

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D2-RPT', 10000);

        $tache = $this->tachePourActivite($activite, 'T-RPT');

        $user = $this->user('gestionnaire_budgetaire');

        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 1500, 'EB-D2-RPT', $tache);



        $engagement = app(EngagementService::class)->creer(

            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'RPT'],

            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 1500]],

            $user,

        );

        $engagement->update(['statut' => 'vise']);



        $reporting = app(DepenseReportingService::class);

        $synthese = $reporting->syntheseBesoinsParTache($this->exercice->id, $tache->id);

        $this->assertSame(1, $synthese['nb']);

        $this->assertSame(1500.0, $synthese['montant']);



        $ventilation = $reporting->ventilerParTache($this->exercice->id, $tache->id);

        $this->assertCount(1, $ventilation);

        $this->assertSame(1500.0, (float) $ventilation->first()->montant_engage);

    }



    public function test_route_dossier_accessible(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D2-HTTP', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 200, 'EB-D2-HTTP');

        $this->actingAs($user)
            ->get(route('depense.dossier.besoin', $besoin))
            ->assertOk()
            ->assertSee('Dossier dépense unifié');
    }
}
