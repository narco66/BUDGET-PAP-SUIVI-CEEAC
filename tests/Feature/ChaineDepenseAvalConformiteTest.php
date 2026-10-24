<?php

namespace Tests\Feature;

use App\Models\ChaineDepenseParametre;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Depense\SeuilOrdonnancementService;
use App\Services\EngagementService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

class ChaineDepenseAvalConformiteTest extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_visa_engagement_cree_une_seule_liquidation(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-AL', 8000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 400, 'EB-AVAL-AL');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Auto LIQ'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 400]],
            $gestionnaire,
        );
        app(EngagementService::class)->soumettre($engagement, $gestionnaire);
        $this->viserEngagement($engagement);

        $this->assertSame(1, Liquidation::where('engagement_id', $engagement->id)->count());
        app(\App\Services\Depense\EngagementVersLiquidationService::class)->assurerDepuisEngagementVise($engagement->fresh());
        $this->assertSame(1, Liquidation::where('engagement_id', $engagement->id)->count());
    }

    public function test_visa_cf_liquidation_exige_service_fait_puis_cree_ord(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-SF', 8000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 300, 'EB-AVAL-SF');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'SF'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 300]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create([
            'numero' => 'LIQ-SF', 'engagement_id' => $engagement->id, 'montant' => 300, 'statut' => 'brouillon',
        ]);
        $wf = app(\App\Services\WorkflowService::class);
        $wf->demarrer($liq, 'LIQUIDATION', $gestionnaire);
        $wf->valider($liq->fresh()->workflowActif, $this->user('chef_service'));

        try {
            $wf->valider($liq->fresh()->workflowActif, $this->user('controleur_financier'));
            $this->fail('Le visa CF sans service fait doit échouer.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('service fait', $e->getMessage());
        }

        $liq->update(['service_fait' => 'Livraison conforme.']);
        $wf->valider($liq->fresh()->workflowActif, $this->user('controleur_financier'));
        $this->assertSame('valide', $liq->fresh()->statut);
        $this->assertSame(1, Ordonnancement::where('liquidation_id', $liq->id)->count());
    }

    public function test_seuil_route_sg_jusqua_cinq_millions_et_president_au_dela(): void
    {
        ChaineDepenseParametre::query()->updateOrCreate(
            ['cle' => 'ordonnancement.seuil_xaf'],
            ['valeur' => '5000000.00', 'version' => 1, 'juridiquement_valide' => false]
        );
        $seuils = app(SeuilOrdonnancementService::class);

        $this->assertSame('secretaire_general', $seuils->snapshotPour(5_000_000)['ordonnateur_role']);
        $this->assertSame('ORDONNANCEMENT', $seuils->snapshotPour(5_000_000)['circuit']);
        $this->assertSame('president', $seuils->snapshotPour(5_000_001)['ordonnateur_role']);
        $this->assertSame('ORDONNANCEMENT_PRESIDENT', $seuils->snapshotPour(5_000_001)['circuit']);
    }

    public function test_paiement_partiel_respecte_le_reliquat(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-PP', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 900, 'EB-AVAL-PP');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'PP'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 900]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-PP', 'engagement_id' => $engagement->id, 'montant' => 900, 'statut' => 'valide', 'compteurs_appliques' => true, 'service_fait' => 'OK']);
        $ord = Ordonnancement::create(['numero' => 'ORD-PP', 'liquidation_id' => $liq->id, 'montant' => 900, 'statut' => 'valide', 'compteurs_appliques' => true]);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 400,
            'mode_paiement' => 'virement',
            'devise' => 'XAF',
            'taux_change' => 1,
            'rib' => 'CM21100000000000000000001',
            'motif_partiel' => 'Trésorerie insuffisante cette semaine',
        ])->assertCreated()->assertJsonPath('item.montant', '400.00');

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 600,
            'mode_paiement' => 'virement',
            'devise' => 'XAF',
            'rib' => 'CM21100000000000000000001',
        ])->assertStatus(422);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 500,
            'mode_paiement' => 'cheque',
            'devise' => 'XAF',
            'reference_bancaire' => 'CHQ-001',
            'motif_partiel' => 'Solde après encaissement contribution',
        ])->assertCreated()->assertJsonPath('item.montant', '500.00');

        $this->assertSame(2, Paiement::where('ordonnancement_id', $ord->id)->count());
    }

    public function test_insuffisance_credits_tracee_sans_creer_de_credit(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-INS', 50);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 80, 'EB-AVAL-INS');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'INS'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 80]],
            $gestionnaire,
        );

        try {
            app(EngagementService::class)->soumettre($engagement, $gestionnaire);
            $this->fail('Insuffisance attendue.');
        } catch (\RuntimeException) {
        }

        $engagement->refresh();
        $this->assertSame('en_attente_arbitrage', $engagement->statut);
        $this->assertFalse($engagement->credits_reserves);
        $this->assertSame('50.00', $engagement->diagnostic_credits['disponible'] ?? null);
        $this->assertEquals(0, (float) $ligne->fresh()->montant_engage);
    }

    public function test_circuit_paiement_exige_comptable_puis_agent_comptable(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-AC', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 200, 'EB-AVAL-AC');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'AC'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 200]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-AC', 'engagement_id' => $engagement->id, 'montant' => 200, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-AC', 'liquidation_id' => $liq->id, 'montant' => 200, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $pay = Paiement::create([
            'numero' => 'PAY-AC', 'ordonnancement_id' => $ord->id, 'montant' => 200,
            'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id,
        ]);

        $wf = app(\App\Services\WorkflowService::class);
        $wf->demarrer($pay, 'PAIEMENT', $gestionnaire);
        $wf->valider($pay->fresh()->workflowActif, $this->user('comptable'));
        $this->assertNotSame('valide', $pay->fresh()->statut);

        $wf->valider($pay->fresh()->workflowActif, $this->user('agent_comptable'));
        $this->assertSame('valide', $pay->fresh()->statut);
    }

    public function test_alerte_fractionnement_sans_blocage(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-FR', 20_000_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 6_000_000, 'EB-AVAL-FR');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'FR'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 6_000_000]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq1 = Liquidation::create(['numero' => 'LIQ-FR1', 'engagement_id' => $engagement->id, 'montant' => 3_000_000, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $liq2 = Liquidation::create(['numero' => 'LIQ-FR2', 'engagement_id' => $engagement->id, 'montant' => 3_000_000, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord1 = Ordonnancement::create(['numero' => 'ORD-FR1', 'liquidation_id' => $liq1->id, 'montant' => 3_000_000, 'statut' => 'brouillon']);
        $ord2 = Ordonnancement::create(['numero' => 'ORD-FR2', 'liquidation_id' => $liq2->id, 'montant' => 3_000_000, 'statut' => 'brouillon']);

        $alerte = app(\App\Services\Depense\FractionnementOrdonnancementService::class)->alerte($ord2->fresh()->load('liquidation.engagement'));
        $this->assertNotNull($alerte);
        $this->assertSame(2, $alerte['nombre']);
        $this->assertSame('brouillon', $ord2->fresh()->statut);
    }

    public function test_liquidation_partielle_reemploie_le_brouillon_auto(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-LP', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 900, 'EB-AVAL-LP');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'LP'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 900]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        app(\App\Services\Depense\EngagementVersLiquidationService::class)->assurerDepuisEngagementVise($engagement);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant' => 400,
            'service_fait' => 'Première livraison',
            'numero_facture' => 'FA-400',
        ])->assertOk()->assertJsonPath('code', 'mis_a_jour')->assertJsonPath('item.montant', '400.00');

        $this->assertSame(1, Liquidation::where('engagement_id', $engagement->id)->count());
        $liq = Liquidation::where('engagement_id', $engagement->id)->first();
        $liq->update(['statut' => 'valide', 'compteurs_appliques' => true]);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant' => 500,
            'service_fait' => 'Solde',
            'numero_facture' => 'FA-500',
        ])->assertCreated()->assertJsonPath('item.montant', '500.00');

        $this->assertSame(2, Liquidation::where('engagement_id', $engagement->id)->count());
    }

    public function test_alerte_facture_doublon_sans_blocage(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-FD', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 200, 'EB-AVAL-FD');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'FD'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 200]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        Liquidation::create([
            'numero' => 'LIQ-FD1', 'engagement_id' => $engagement->id, 'montant' => 80,
            'statut' => 'valide', 'numero_facture' => 'FA-DUP', 'compteurs_appliques' => true,
        ]);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant' => 80,
            'numero_facture' => 'FA-DUP',
        ])->assertCreated();

        $liq2 = Liquidation::where('engagement_id', $engagement->id)->latest('id')->first();
        $alerte = app(\App\Services\Depense\FactureDoublonService::class)->alerte($liq2);
        $this->assertNotNull($alerte);
        $this->assertSame('FA-DUP', $alerte['numero_facture']);
    }

    public function test_degagement_plafonne_au_non_liquide(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-DEG', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 1000, 'EB-AVAL-DEG');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'DEG'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 1000]],
            $gestionnaire,
        );
        app(EngagementService::class)->soumettre($engagement, $gestionnaire);
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        Liquidation::create([
            'numero' => 'LIQ-DEG', 'engagement_id' => $engagement->id, 'montant' => 400,
            'statut' => 'valide', 'compteurs_appliques' => true,
        ]);

        $this->actingAs($gestionnaire)->postJson(route('api.engagements.degager', $engagement), [
            'montant' => 700,
        ])->assertStatus(422);

        $this->actingAs($gestionnaire)->postJson(route('api.engagements.degager', $engagement), [
            'montant' => 200,
        ])->assertOk()->assertJsonPath('code', 'degage');

        $this->assertEqualsWithDelta(800.0, (float) $engagement->fresh()->montant_total, 0.05);
        $this->assertEqualsWithDelta(800.0, (float) $ligne->fresh()->montant_engage, 0.05);
    }

    public function test_cloture_chaine_quand_tout_est_paye(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-CL', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 150, 'EB-AVAL-CL');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'CL'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 150]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-CL', 'engagement_id' => $engagement->id, 'montant' => 150, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-CL', 'liquidation_id' => $liq->id, 'montant' => 150, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $pay = Paiement::create([
            'numero' => 'PAY-CL', 'ordonnancement_id' => $ord->id, 'montant' => 150,
            'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id,
        ]);
        $this->viserPaiement($pay);

        $this->assertNotNull($engagement->fresh()->chaine_cloturee_le);
        $this->assertTrue(app(\App\Services\Depense\ChaineClotureService::class)->etat($engagement->fresh())['soldee']);
    }

    public function test_paiement_partiel_exige_un_motif(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-MOT', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 300, 'EB-AVAL-MOT');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'MOT'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 300]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-MOT', 'engagement_id' => $engagement->id, 'montant' => 300, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-MOT', 'liquidation_id' => $liq->id, 'montant' => 300, 'statut' => 'valide', 'compteurs_appliques' => true]);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 100,
            'mode_paiement' => 'virement',
            'devise' => 'XAF',
            'rib' => 'CM21100000000000000000002',
        ])->assertStatus(422);
    }

    public function test_virement_exige_un_rib(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-RIB', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 90, 'EB-AVAL-RIB');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'RIB'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 90]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-RIB', 'engagement_id' => $engagement->id, 'montant' => 90, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-RIB', 'liquidation_id' => $liq->id, 'montant' => 90, 'statut' => 'valide', 'compteurs_appliques' => true]);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 90,
            'mode_paiement' => 'virement',
            'devise' => 'XAF',
        ])->assertStatus(422);
    }

    public function test_rejet_instrument_contrepassation_sans_effacement(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-CTR', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 150, 'EB-AVAL-CTR');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'CTR'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 150]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-CTR', 'engagement_id' => $engagement->id, 'montant' => 150, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-CTR', 'liquidation_id' => $liq->id, 'montant' => 150, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $pay = Paiement::create([
            'numero' => 'PAY-CTR', 'ordonnancement_id' => $ord->id, 'montant' => 150,
            'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id,
        ]);
        $this->viserPaiement($pay);
        $this->assertNotNull($engagement->fresh()->chaine_cloturee_le);

        $payeAvant = (float) $ligne->fresh()->montant_paye;
        $this->actingAs($this->user('comptable'))->postJson(route('api.depense.rejet-instrument', $pay), [
            'motif' => 'Virement rejeté par la banque',
            'preuve' => 'AVIS-RJ-1',
        ])->assertOk()->assertJsonPath('code', 'instrument_rejete');

        $pay->refresh();
        $this->assertSame('valide', $pay->statut);
        $this->assertSame('instrument_rejete', $pay->etat_execution);
        $this->assertTrue($pay->rejets()->where('type', 'instrument')->exists());
        $this->assertEqualsWithDelta(0.0, (float) $ligne->fresh()->montant_paye, 0.05);
        $this->assertGreaterThan($payeAvant - 0.05, 150);
        $this->assertEqualsWithDelta(150.0, app(\App\Services\Depense\DepenseQuotaService::class)->montantRestantPaiement($ord->fresh()), 0.05);
        $this->assertNull($engagement->fresh()->chaine_cloturee_le);
        $this->assertFalse(app(\App\Services\Depense\ChaineClotureService::class)->etat($engagement->fresh())['soldee']);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 150,
            'mode_paiement' => 'virement',
            'devise' => 'XAF',
            'rib' => 'CM21100000000000000000009',
        ])->assertCreated();
    }

    public function test_paiement_refuse_si_exercice_clos(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-CLX', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 70, 'EB-AVAL-CLX');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'CLX'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 70]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-CLX', 'engagement_id' => $engagement->id, 'montant' => 70, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-CLX', 'liquidation_id' => $liq->id, 'montant' => 70, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $this->exercice->update(['statut' => 'clos']);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 70,
            'mode_paiement' => 'especes',
            'devise' => 'XAF',
        ])->assertStatus(422);
    }

    public function test_fiche_pdf_et_page_de_verification_interne(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-QR', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 80, 'EB-AVAL-QR');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'QR'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 80]],
            $gestionnaire,
        );

        $bin = app(\App\Services\DocumentPdf::class)->engagement($engagement)->output();
        $this->assertNotEmpty($bin);

        $this->actingAs($gestionnaire)->get(route('depense.verifier', ['type' => 'engagement', 'id' => $engagement->id]))
            ->assertOk()
            ->assertSee('Vérification interne', false)
            ->assertSee($engagement->numero, false);

        $this->actingAs($gestionnaire)->get(route('api.engagements.pdf', $engagement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($gestionnaire)->get(route('api.engagements.certificat', $engagement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_rapprochement_ne_confond_pas_avec_le_visa(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-RAP', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 120, 'EB-AVAL-RAP');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'RAP'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 120]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-RAP', 'engagement_id' => $engagement->id, 'montant' => 120, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-RAP', 'liquidation_id' => $liq->id, 'montant' => 120, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $pay = Paiement::create([
            'numero' => 'PAY-RAP', 'ordonnancement_id' => $ord->id, 'montant' => 120,
            'mode_paiement' => 'virement', 'reference_bancaire' => 'REF-RAP-1',
            'statut' => 'valide', 'etat_execution' => 'execute', 'cree_par' => $gestionnaire->id,
        ]);

        $service = app(\App\Services\Depense\RapprochementBancaireService::class);
        $session = $service->creer($this->exercice, ['intitule' => 'Relevé RAP'], $this->user('comptable'));
        $service->importerLignes($session, [[
            'date_operation' => now()->toDateString(),
            'libelle' => 'Virement',
            'reference_bancaire' => 'REF-RAP-1',
            'montant' => 120,
        ]]);
        $this->assertSame(1, $service->rapprocherAuto($session->fresh()));
        $pay->refresh();
        $this->assertSame('valide', $pay->statut);
        $this->assertSame('rapproche', $pay->etat_execution);
        $this->assertNotNull($pay->rapprochement_bancaire_id);
    }

    public function test_liquidation_brut_retenues_net_sans_bareme(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-BN', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 500, 'EB-AVAL-BN');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'BN'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 500]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant_brut' => 500,
            'montant_retenues' => 50,
            'montant_net' => 400,
        ])->assertStatus(422);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant_brut' => 500,
            'montant_retenues' => 50,
            'montant_net' => 450,
        ])->assertCreated()->assertJsonPath('item.montant', '450.00');
    }

    public function test_visa_budget_refuse_si_structure_non_budget(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-DIR', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 60, 'EB-AVAL-DIR');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'DIR'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 60]],
            $gestionnaire,
        );
        app(EngagementService::class)->soumettre($engagement, $gestionnaire);

        $horsBudget = \App\Models\Structure::factory()->create(['code' => 'SG-HORS', 'sigle' => 'SG']);
        $directeur = $this->user('directeur');
        $directeur->update(['structure_id' => $horsBudget->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Direction du Budget');
        app(\App\Services\WorkflowService::class)->valider($engagement->fresh()->workflowActif, $directeur);
    }

    public function test_paiement_refuse_si_ligne_inactive(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-INA', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 70, 'EB-AVAL-INA');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'INA'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 70]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-INA', 'engagement_id' => $engagement->id, 'montant' => 70, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-INA', 'liquidation_id' => $liq->id, 'montant' => 70, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ligne->update(['actif' => false]);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 70,
            'mode_paiement' => 'especes',
            'devise' => 'XAF',
        ])->assertStatus(422)->assertJsonFragment(['code' => 'credits_ou_transition']);
    }

    public function test_delegation_sg_autorise_le_visa_ordonnancement(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $sg = $this->user('secretaire_general');
        $structureSg = \App\Models\Structure::query()->firstOrNew(['code' => 'SG']);
        $structureSg->fill([
            'libelle' => $structureSg->libelle ?: 'Secrétariat général',
            'sigle' => $structureSg->sigle ?: 'SG',
            'type' => $structureSg->type ?: 'secretariat',
            'actif' => true,
            'responsable_user_id' => $sg->id,
        ])->save();
        $delegue = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        app(\App\Services\Institution\InstitutionGouvernanceService::class)->creerDelegation([
            'structure_id' => $structureSg->id,
            'delegue_par' => $sg->id,
            'delegue_a' => $delegue->id,
            'date_debut' => now()->toDateString(),
            'motif' => 'Délégation écrite d’ordonnancement',
        ]);

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-DEL', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 120, 'EB-AVAL-DEL');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'DEL'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 120]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-DEL', 'engagement_id' => $engagement->id, 'montant' => 120, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-DEL', 'liquidation_id' => $liq->id, 'montant' => 120, 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id]);

        $wf = app(\App\Services\WorkflowService::class);
        $wf->demarrer($ord, 'ORDONNANCEMENT', $gestionnaire);
        $this->assertTrue($wf->peutAgir($ord->fresh()->workflowActif, $delegue));
        $wf->valider($ord->fresh()->workflowActif, $delegue);
        $this->assertSame('valide', $ord->fresh()->statut);
        $this->assertFalse($delegue->hasRole('secretaire_general'));
    }

    public function test_empreintes_chaine_depense_sans_secrets(): void
    {
        $this->artisan('chaine-depense:empreintes')
            ->assertSuccessful()
            ->expectsOutputToContain('empreinte_ids_users')
            ->expectsOutputToContain('empreinte_noms_roles');
    }

    public function test_visa_ord_cree_un_seul_paiement_en_preparation(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-PAYA', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 110, 'EB-AVAL-PAYA');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'PAYA'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 110]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-PAYA', 'engagement_id' => $engagement->id, 'montant' => 110, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-PAYA', 'liquidation_id' => $liq->id, 'montant' => 110, 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id]);
        $this->viserOrdonnancement($ord);

        $this->assertSame(1, Paiement::where('ordonnancement_id', $ord->id)->count());
        $pay = Paiement::where('ordonnancement_id', $ord->id)->first();
        $this->assertSame('brouillon', $pay->statut);
        $this->assertSame('en_preparation', $pay->etat_execution);

        app(\App\Services\Depense\OrdonnancementVersPaiementService::class)->assurerDossierDepuisOrdreSigne($ord->fresh());
        $this->assertSame(1, Paiement::where('ordonnancement_id', $ord->id)->count());

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 110,
            'mode_paiement' => 'especes',
            'devise' => 'XAF',
        ])->assertStatus(422);
    }

    public function test_reprise_engagement_apres_operation_budgetaire_reguliere(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-REP', 40);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 80, 'EB-AVAL-REP');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'REP'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 80]],
            $gestionnaire,
        );

        try {
            app(EngagementService::class)->soumettre($engagement, $gestionnaire);
            $this->fail('Insuffisance attendue.');
        } catch (\RuntimeException) {
        }

        $this->assertSame('en_attente_arbitrage', $engagement->fresh()->statut);
        $ligne->update(['montant_ouvert' => 500]);

        $this->actingAs($gestionnaire)->postJson(route('api.engagements.soumettre', $engagement))
            ->assertOk();

        $engagement->refresh();
        $this->assertSame('soumis', $engagement->statut);
        $this->assertTrue($engagement->credits_reserves);
        $this->assertNull($engagement->diagnostic_credits);
        $this->assertEqualsWithDelta(80.0, (float) $ligne->fresh()->montant_engage, 0.05);
    }

    public function test_delegation_president_autorise_le_visa_ordonnancement(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $president = $this->user('president');
        $structurePres = \App\Models\Structure::query()->firstOrNew(['code' => 'PRES']);
        $structurePres->fill([
            'libelle' => $structurePres->libelle ?: 'Présidence',
            'sigle' => $structurePres->sigle ?: 'PRES',
            'type' => $structurePres->type ?: 'presidence',
            'actif' => true,
            'responsable_user_id' => $president->id,
        ])->save();
        $delegue = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        app(\App\Services\Institution\InstitutionGouvernanceService::class)->creerDelegation([
            'structure_id' => $structurePres->id,
            'delegue_par' => $president->id,
            'delegue_a' => $delegue->id,
            'date_debut' => now()->toDateString(),
            'motif' => 'Délégation écrite d’ordonnancement Président',
        ]);

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-PRESD', 8_000_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 5_000_001, 'EB-AVAL-PRESD');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'PRESD'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 5_000_001]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-PRESD', 'engagement_id' => $engagement->id, 'montant' => 5_000_001, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-PRESD', 'liquidation_id' => $liq->id, 'montant' => 5_000_001, 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id]);

        $wf = app(\App\Services\WorkflowService::class);
        $wf->demarrer($ord, 'ORDONNANCEMENT_PRESIDENT', $gestionnaire);
        $this->assertTrue($wf->peutAgir($ord->fresh()->workflowActif, $delegue));
        $wf->valider($ord->fresh()->workflowActif, $delegue);
        $this->assertSame('valide', $ord->fresh()->statut);
        $this->assertFalse($delegue->hasRole('president'));
    }

    public function test_interim_sg_autorise_le_visa_ordonnancement(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $sg = $this->user('secretaire_general');
        $structureSg = \App\Models\Structure::query()->firstOrNew(['code' => 'SG']);
        $structureSg->fill([
            'libelle' => $structureSg->libelle ?: 'Secrétariat général',
            'sigle' => $structureSg->sigle ?: 'SG',
            'type' => $structureSg->type ?: 'secretariat',
            'actif' => true,
            'responsable_user_id' => $sg->id,
        ])->save();
        $interim = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        app(\App\Services\Institution\InstitutionGouvernanceService::class)->creerInterim([
            'structure_id' => $structureSg->id,
            'titulaire_user_id' => $sg->id,
            'interim_user_id' => $interim->id,
            'date_debut' => now()->toDateString(),
            'motif' => 'Intérim d’ordonnancement SG',
        ]);

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-INT', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 95, 'EB-AVAL-INT');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'INT'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 95]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-INT', 'engagement_id' => $engagement->id, 'montant' => 95, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-INT', 'liquidation_id' => $liq->id, 'montant' => 95, 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id]);

        $wf = app(\App\Services\WorkflowService::class);
        $wf->demarrer($ord, 'ORDONNANCEMENT', $gestionnaire);
        $this->assertTrue($wf->peutAgir($ord->fresh()->workflowActif, $interim));
        $wf->valider($ord->fresh()->workflowActif, $interim);
        $this->assertSame('valide', $ord->fresh()->statut);
        $this->assertFalse($interim->hasRole('secretaire_general'));
    }

    public function test_if_match_refuse_une_soumission_engagement_perimee(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-IFM', 3000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 40, 'EB-AVAL-IFM');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'IFM'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 40]],
            $gestionnaire,
        );
        $this->actingAs($gestionnaire)
            ->withHeaders(['If-Match' => '2000-01-01T00:00:00+00:00'])
            ->postJson(route('api.engagements.soumettre', $engagement))
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');
        $this->assertFalse((bool) $engagement->fresh()->credits_reserves);
    }

    public function test_fiche_liquidation_pdf_et_verification_interne(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-LIQQR', 2000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 35, 'EB-AVAL-LIQQR');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'LIQQR'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 35]],
            $gestionnaire,
        );
        $liq = Liquidation::create([
            'numero' => 'LIQ-QR', 'engagement_id' => $engagement->id, 'montant' => 35, 'statut' => 'brouillon',
        ]);
        $config = \App\Depense\Etapes::get('liquidation');
        $bin = app(\App\Services\DocumentPdf::class)->etapeDepense($liq, $config)->output();
        $this->assertNotEmpty($bin);

        $this->actingAs($gestionnaire)->get(route('depense.verifier', ['type' => 'liquidation', 'id' => $liq->id]))
            ->assertOk()
            ->assertSee('Vérification interne', false)
            ->assertSee($liq->numero, false);

        $this->actingAs($gestionnaire)->get(route('api.depense.pdf', ['type' => 'liquidation', 'id' => $liq->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_seuil_ordonnancement_n_est_pas_retroactif(): void
    {
        ChaineDepenseParametre::query()->updateOrCreate(
            ['cle' => 'ordonnancement.seuil_xaf'],
            ['valeur' => '5000000.00', 'version' => 1, 'juridiquement_valide' => false]
        );
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-NR', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 80, 'EB-AVAL-NR');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'NR'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 80]],
            $gestionnaire,
        );
        $liq = Liquidation::create(['numero' => 'LIQ-NR', 'engagement_id' => $engagement->id, 'montant' => 80, 'statut' => 'valide']);
        $ord = Ordonnancement::create(['numero' => 'ORD-NR', 'liquidation_id' => $liq->id, 'montant' => 80, 'statut' => 'brouillon']);
        app(SeuilOrdonnancementService::class)->appliquer($ord);
        $this->assertSame('secretaire_general', $ord->fresh()->ordonnateur_role);

        ChaineDepenseParametre::query()->where('cle', 'ordonnancement.seuil_xaf')->update([
            'valeur' => '50.00',
            'version' => 2,
        ]);
        $this->assertSame('ORDONNANCEMENT', app(SeuilOrdonnancementService::class)->circuit($ord->fresh()));
        $this->assertSame('secretaire_general', $ord->fresh()->ordonnateur_role);
        $this->assertSame(1, (int) $ord->fresh()->seuil_version);
    }

    public function test_sod_interdit_le_meme_acteur_sur_deux_etapes_paiement(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $acteur = $this->user('comptable');
        $acteur->assignRole('agent_comptable');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-SOD', 5000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 55, 'EB-AVAL-SOD');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'SOD'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 55]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-SOD', 'engagement_id' => $engagement->id, 'montant' => 55, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-SOD', 'liquidation_id' => $liq->id, 'montant' => 55, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $pay = Paiement::create([
            'numero' => 'PAY-SOD', 'ordonnancement_id' => $ord->id, 'montant' => 55,
            'mode_paiement' => 'especes', 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id,
        ]);

        $wf = app(\App\Services\WorkflowService::class);
        $wf->demarrer($pay, 'PAIEMENT', $gestionnaire);
        $wf->valider($pay->fresh()->workflowActif, $acteur);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Séparation des fonctions');
        $wf->valider($pay->fresh()->workflowActif, $acteur);
    }

    public function test_changement_rib_refuse_le_visa_du_saisisseur(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AVAL-RIB2', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 200, 'EB-AVAL-RIB2');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'RIB2'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 200]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-RIB2', 'engagement_id' => $engagement->id, 'montant' => 200, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-RIB2', 'liquidation_id' => $liq->id, 'montant' => 200, 'statut' => 'valide', 'compteurs_appliques' => true]);
        Paiement::create([
            'numero' => 'PAY-RIB2A', 'ordonnancement_id' => $ord->id, 'montant' => 80,
            'mode_paiement' => 'virement', 'rib' => 'CM21100000000000000000001',
            'statut' => 'valide', 'cree_par' => $gestionnaire->id, 'compteurs_appliques' => true,
        ]);

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'paiement'), [
            'parent' => $ord->id,
            'montant' => 80,
            'mode_paiement' => 'virement',
            'devise' => 'XAF',
            'rib' => 'CM21100000000000000000099',
            'motif_partiel' => 'Second virement RIB distinct',
        ])->assertCreated();
        $pay = Paiement::query()->where('ordonnancement_id', $ord->id)->latest('id')->first();
        $this->assertNotNull(app(\App\Services\Depense\RibControleService::class)->alerte($pay));

        $wf = app(\App\Services\WorkflowService::class);
        $wf->demarrer($pay, 'PAIEMENT', $gestionnaire);
        $wf->valider($pay->fresh()->workflowActif, $this->user('comptable'));

        $gestionnaire->assignRole('agent_comptable');
        $this->actingAs($gestionnaire)->postJson(route('api.depense.decisions', ['paiement', $pay->id]), [
            'decision' => 'valider',
        ])->assertStatus(422);

        $this->actingAs($this->user('agent_comptable'))->postJson(route('api.depense.decisions', ['paiement', $pay->id]), [
            'decision' => 'valider',
        ])->assertOk();
    }
}
