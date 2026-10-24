<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

class DepenseAvalTest extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private Exercice $exercice;
    private LigneBudgetaire $ligne;
    private Engagement $engagement;
    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(DepensePermissionsSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->workflow = app(WorkflowService::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);

        [$activite, $this->ligne] = $this->activiteEtLigne($this->exercice, 'LB-A', 1000);

        // engagement visé avec imputation 600 (crédits réservés)
        $svc = app(EngagementService::class);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $this->ligne, $user, 600, 'EB-AVAL');
        $this->engagement = $svc->creer(
            [
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'type' => 'simple',
                'objet' => 'T',
            ],
            [['ligne_budgetaire_id' => $this->ligne->id, 'montant' => 600]],
            $user,
        );
        $svc->soumettre($this->engagement, $this->user('gestionnaire_budgetaire'));
        $this->viserEngagement($this->engagement);
        $this->engagement->refresh();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_chaine_complete_met_a_jour_les_compteurs(): void
    {
        $this->assertSame('vise', $this->engagement->statut);
        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_engage);

        $liq = $this->engagement->liquidations()->first();
        $this->assertNotNull($liq);
        $this->viserLiquidation($liq);
        $this->assertSame('valide', $liq->fresh()->statut);
        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_liquide);

        $ord = $liq->fresh()->ordonnancements()->first();
        $this->assertNotNull($ord);
        $this->viserOrdonnancement($ord);
        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_ordonnance);

        $pay = $ord->fresh()->paiements()->first();
        $this->assertNotNull($pay);
        $this->viserPaiement($pay);

        $l = $this->ligne->fresh();
        $this->assertEquals(600, (float) $l->montant_paye);
        $this->assertEquals([600, 600, 600, 600], [
            (float) $l->montant_engage, (float) $l->montant_liquide,
            (float) $l->montant_ordonnance, (float) $l->montant_paye,
        ]);
    }

    public function test_separation_des_fonctions_sur_l_ordonnancement(): void
    {
        $liq = $this->engagement->liquidations()->first();
        $this->viserLiquidation($liq);
        $ord = $liq->fresh()->ordonnancements()->first();
        $circuit = app(\App\Services\Depense\SeuilOrdonnancementService::class)->circuit($ord);
        $this->workflow->demarrer($ord, $circuit, $this->user('gestionnaire_budgetaire'));

        $this->assertFalse($this->workflow->peutAgir($ord->workflowActif, $this->user('controleur_financier')));
        $this->expectException(RuntimeException::class);
        $this->workflow->valider($ord->workflowActif, $this->user('controleur_financier'));
    }

    public function test_compteur_idempotent(): void
    {
        $liq = $this->engagement->liquidations()->first();
        $this->viserLiquidation($liq);

        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_liquide);

        $liq->fresh()->auWorkflowValide();
        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_liquide);
    }

    public function test_creation_ordonnancement_refusee_si_liquidation_non_validee(): void
    {
        $liq = $this->engagement->liquidations()->first();

        $reponse = $this->actingAs($this->user('administrateur'))
            ->get(route('depense.create', 'ordonnancement').'?parent='.$liq->id);
        $this->assertTrue(
            in_array($reponse->status(), [302, 422], true),
            'La création ORD sur LIQ non validée doit être refusée (422 métier ou 302 auth web).'
        );
        $this->assertSame('brouillon', $liq->fresh()->statut);
    }

    public function test_creation_liquidation_via_http_depuis_engagement_vise(): void
    {
        $this->assertDatabaseHas('liquidations', [
            'engagement_id' => $this->engagement->id, 'montant' => 600, 'statut' => 'brouillon',
        ]);
    }

    public function test_deuxieme_liquidation_refusee_si_plafond_atteint(): void
    {
        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->post(route('depense.store', 'liquidation'), [
                'parent' => $this->engagement->id,
                'montant' => 100,
                'service_fait' => 'Trop.',
                'date' => '2026-03-02',
            ])
            ->assertStatus(422);
    }

    public function test_liquidation_partielle_autorisee(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-P', 5000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 600, 'EB-AVAL-P');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Partiel'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 600]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);

        $this->actingAs($gestionnaire)
            ->post(route('depense.store', 'liquidation'), [
                'parent' => $engagement->id,
                'montant' => 400,
                'service_fait' => 'Partielle.',
                'date' => '2026-03-01',
            ])->assertRedirect();

        $this->assertDatabaseHas('liquidations', [
            'engagement_id' => $engagement->id,
            'montant' => 400,
        ]);

        $this->assertEquals(200.0, $engagement->fresh()->montantRestantLiquidation());
    }
}
