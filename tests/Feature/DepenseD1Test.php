<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\ReservationCredit;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Depense\BesoinConsolidationService;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Phase D1 — workflow besoin multi-étapes, consolidation, réservations, policies, exports. */
class DepenseD1Test extends TestCase
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

    public function test_workflow_besoin_trois_etapes(): void
    {
        $etapes = Workflow::firstWhere('code', 'BESOIN')?->etapes()->orderBy('ordre')->get();
        $this->assertCount(3, $etapes);
        $this->assertSame('chef_service', $etapes[0]->role);
        $this->assertSame('directeur', $etapes[1]->role);
        $this->assertSame('controleur_financier', $etapes[2]->role);
    }

    public function test_besoin_valide_apres_trois_visas(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D1-WF', 5000);
        $demandeur = $this->user('chef_service');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $demandeur, 1000, 'EB-D1-WF');
        $besoin->update(['statut' => 'brouillon']);

        $this->workflow->demarrer($besoin, 'BESOIN', $demandeur);
        $instance = $besoin->fresh()->workflowActif;

        $this->workflow->valider($instance, $this->user('chef_service'));
        $this->workflow->valider($instance->fresh(), $this->user('directeur'));
        $this->workflow->valider($instance->fresh(), $this->user('controleur_financier'));

        $this->assertSame('valide', $besoin->fresh()->statut);
    }

    public function test_reservation_credits_persistee_a_la_soumission(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D1-RES', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 800, 'EB-D1-RES');
        $svc = app(EngagementService::class);

        $engagement = $svc->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Test réservation'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 800]],
            $user,
        );
        $svc->soumettre($engagement, $user);

        $this->assertDatabaseHas('reservations_credits', [
            'engagement_id' => $engagement->id,
            'ligne_budgetaire_id' => $ligne->id,
            'statut' => 'active',
        ]);
        $this->assertDatabaseHas('certificats_engagement', [
            'engagement_id' => $engagement->id,
            'type' => 'reservation',
        ]);
    }

    public function test_certificat_engagement_a_la_validation(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D1-CER', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 500, 'EB-D1-CER');
        $svc = app(EngagementService::class);

        $engagement = $svc->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Certificat'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 500]],
            $user,
        );
        $svc->soumettre($engagement, $user);
        $this->viserEngagement($engagement);

        $this->assertDatabaseHas('certificats_engagement', [
            'engagement_id' => $engagement->id,
            'type' => 'engagement',
        ]);
    }

    public function test_consolidation_besoins_detecte_doublons(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D1-CON', 10000);
        $user = $this->user('chef_service');

        ExpressionBesoin::create([
            'numero' => 'EB-D1-A', 'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id, 'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => $user->id, 'objet' => 'Fournitures bureau',
            'montant_estime' => 1000, 'statut' => 'valide',
        ]);
        ExpressionBesoin::create([
            'numero' => 'EB-D1-B', 'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id, 'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => $user->id, 'objet' => 'Fournitures bureau',
            'montant_estime' => 1000, 'statut' => 'soumis',
        ]);

        $synthese = app(BesoinConsolidationService::class)->synthese($this->exercice->id);
        $this->assertSame(2, $synthese['total_besoins']);
        $this->assertSame(1, $synthese['doublons_count']);

        $consolidation = app(BesoinConsolidationService::class)->consolider($this->exercice->id, null, $user->id);
        $this->assertSame(2, $consolidation->besoins_count);
        $this->assertSame(1, $consolidation->doublons_elimines);
    }

    public function test_policy_liquidation_agir(): void
    {
        config(['chaine_depense.automatisation.demarrage_liquidation' => false]);   // parcours manuel du brouillon de liquidation
        $cf = $this->user('controleur_financier');
        $gest = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D1-POL', 3000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gest, 600, 'EB-D1-POL');
        $svc = app(EngagementService::class);
        $engagement = $svc->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Pol'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 600]],
            $gest,
        );
        $svc->soumettre($engagement, $gest);
        $this->viserEngagement($engagement);

        $liq = $engagement->fresh()->liquidations()->first();
        $liq->update(['service_fait' => 'Service fait.']);
        $this->workflow->demarrer($liq, 'LIQUIDATION', $gest);
        $this->workflow->valider($liq->fresh()->workflowActif, $this->user('chef_service'));

        $this->assertTrue($cf->can('agir', $liq));
        $this->assertFalse($gest->can('agir', $liq));
    }

    public function test_export_besoins_excel(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D1-EXP', 2000);
        $this->besoinValide($this->exercice, $activite, $ligne, $this->user('chef_service'), 500, 'EB-D1-EXP');

        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->get(route('depense.export.besoins', ['exercice' => 2026]))
            ->assertOk();
    }

    public function test_liberation_reservation_a_l_annulation(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D1-LIB', 3000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 400, 'EB-D1-LIB');
        $svc = app(EngagementService::class);
        $engagement = $svc->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Annul'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 400]],
            $user,
        );
        $svc->soumettre($engagement, $user);
        $svc->annuler($engagement->fresh(), $user);

        $this->assertSame(0, ReservationCredit::where('engagement_id', $engagement->id)->where('statut', 'active')->count());
    }
}
