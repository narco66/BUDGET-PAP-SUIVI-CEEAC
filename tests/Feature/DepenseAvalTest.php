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
        $this->workflow->valider($this->engagement->workflowActif, $this->user('controleur_financier'));
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

        // Liquidation → visa contrôleur financier
        $liq = Liquidation::create(['numero' => 'LIQ-1', 'engagement_id' => $this->engagement->id, 'montant' => 600, 'statut' => 'brouillon']);
        $this->workflow->demarrer($liq, 'LIQUIDATION', $this->user('gestionnaire_budgetaire'));
        $this->workflow->valider($liq->workflowActif, $this->user('controleur_financier'));
        $this->assertSame('valide', $liq->fresh()->statut);
        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_liquide);

        // Ordonnancement → ordonnateur (SG)
        $ord = Ordonnancement::create(['numero' => 'ORD-1', 'liquidation_id' => $liq->id, 'montant' => 600, 'statut' => 'brouillon']);
        $this->workflow->demarrer($ord, 'ORDONNANCEMENT', $this->user('gestionnaire_budgetaire'));
        $this->workflow->valider($ord->workflowActif, $this->user('secretaire_general'));
        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_ordonnance);

        // Paiement → comptable
        $pay = Paiement::create(['numero' => 'PAY-1', 'ordonnancement_id' => $ord->id, 'montant' => 600, 'mode_paiement' => 'virement', 'statut' => 'brouillon']);
        $this->workflow->demarrer($pay, 'PAIEMENT', $this->user('gestionnaire_budgetaire'));
        $this->workflow->valider($pay->workflowActif, $this->user('comptable'));

        $l = $this->ligne->fresh();
        $this->assertEquals(600, (float) $l->montant_paye);
        // invariant : payé ≤ ordonnancé ≤ liquidé ≤ engagé
        $this->assertEquals([600, 600, 600, 600], [
            (float) $l->montant_engage, (float) $l->montant_liquide,
            (float) $l->montant_ordonnance, (float) $l->montant_paye,
        ]);
    }

    public function test_separation_des_fonctions_sur_l_ordonnancement(): void
    {
        $liq = Liquidation::create(['numero' => 'LIQ-2', 'engagement_id' => $this->engagement->id, 'montant' => 600, 'statut' => 'brouillon']);
        $this->workflow->demarrer($liq, 'LIQUIDATION', $this->user('gestionnaire_budgetaire'));
        $this->workflow->valider($liq->workflowActif, $this->user('controleur_financier'));

        $ord = Ordonnancement::create(['numero' => 'ORD-2', 'liquidation_id' => $liq->id, 'montant' => 600, 'statut' => 'brouillon']);
        $this->workflow->demarrer($ord, 'ORDONNANCEMENT', $this->user('gestionnaire_budgetaire'));

        // le contrôleur financier ne peut PAS ordonnancer (rôle ordonnateur requis)
        $this->assertFalse($this->workflow->peutAgir($ord->workflowActif, $this->user('controleur_financier')));
        $this->expectException(RuntimeException::class);
        $this->workflow->valider($ord->workflowActif, $this->user('controleur_financier'));
    }

    public function test_compteur_idempotent(): void
    {
        $liq = Liquidation::create(['numero' => 'LIQ-3', 'engagement_id' => $this->engagement->id, 'montant' => 600, 'statut' => 'brouillon']);
        $this->workflow->demarrer($liq, 'LIQUIDATION', $this->user('gestionnaire_budgetaire'));
        $this->workflow->valider($liq->workflowActif, $this->user('controleur_financier'));

        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_liquide);

        // ré-appliquer ne double pas (compteurs_appliques = true)
        $liq->fresh()->auWorkflowValide();
        $this->assertEquals(600, (float) $this->ligne->fresh()->montant_liquide);
    }

    public function test_creation_ordonnancement_refusee_si_liquidation_non_validee(): void
    {
        $liq = Liquidation::create(['numero' => 'LIQ-4', 'engagement_id' => $this->engagement->id, 'montant' => 600, 'statut' => 'brouillon']);

        $this->actingAs($this->user('administrateur'))
            ->get(route('depense.create', 'ordonnancement').'?parent='.$liq->id)
            ->assertStatus(422);
    }

    public function test_creation_liquidation_via_http_depuis_engagement_vise(): void
    {
        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->post(route('depense.store', 'liquidation'), [
                'parent' => $this->engagement->id,
                'montant' => 600,
                'service_fait' => 'Service constaté.',
                'date' => '2026-03-01',
            ])->assertRedirect();

        $this->assertDatabaseHas('liquidations', [
            'engagement_id' => $this->engagement->id, 'montant' => 600, 'statut' => 'brouillon',
        ]);
    }

    public function test_deuxieme_liquidation_refusee_si_plafond_atteint(): void
    {
        Liquidation::create([
            'numero' => 'LIQ-PLEIN',
            'engagement_id' => $this->engagement->id,
            'montant' => 600,
            'statut' => 'brouillon',
        ]);

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
        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->post(route('depense.store', 'liquidation'), [
                'parent' => $this->engagement->id,
                'montant' => 400,
                'service_fait' => 'Partielle.',
                'date' => '2026-03-01',
            ])->assertRedirect();

        $this->assertDatabaseHas('liquidations', [
            'engagement_id' => $this->engagement->id,
            'montant' => 400,
        ]);

        $this->assertEquals(200.0, $this->engagement->fresh()->montantRestantLiquidation());
    }
}
