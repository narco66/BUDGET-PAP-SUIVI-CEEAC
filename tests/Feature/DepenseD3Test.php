<?php

namespace Tests\Feature;

use App\Models\AuditDepense;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\HistoriqueDepense;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\PaiementDetail;
use App\Models\User;
use App\Services\Depense\DepenseAuditService;
use App\Services\Depense\JournalConsolideService;
use App\Services\Depense\RapprochementBancaireService;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Phase D3 — rapprochement bancaire, audit, historique, journaux consolidés. */
class DepenseD3Test extends TestCase
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

    /** @return array{0:Engagement,1:Liquidation,2:Ordonnancement,3:Paiement} */
    private function chaineComplete(float $montant = 500): array
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D3-'.uniqid(), 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, $montant, 'EB-D3-'.uniqid());

        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Chaine D3'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => $montant]],
            $user,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);

        $liq = Liquidation::create([
            'numero' => 'LIQ-D3-'.uniqid(), 'engagement_id' => $engagement->id, 'montant' => $montant,
            'date_liquidation' => now(), 'statut' => 'valide', 'cree_par' => $user->id, 'compteurs_appliques' => true,
        ]);

        $ord = Ordonnancement::create([
            'numero' => 'ORD-D3-'.uniqid(), 'liquidation_id' => $liq->id, 'montant' => $montant,
            'date_ordonnancement' => now(), 'statut' => 'valide', 'cree_par' => $user->id, 'compteurs_appliques' => true,
        ]);

        $pay = Paiement::create([
            'numero' => 'PAY-D3-'.uniqid(), 'ordonnancement_id' => $ord->id, 'montant' => $montant,
            'date_paiement' => now(), 'mode_paiement' => 'virement', 'reference_bancaire' => 'REF-D3-001',
            'statut' => 'valide', 'cree_par' => $user->id, 'compteurs_appliques' => true,
        ]);

        return [$engagement, $liq, $ord, $pay];
    }

    public function test_historique_enregistre_evenement(): void
    {
        [$engagement] = $this->chaineComplete();
        app(\App\Services\Depense\HistoriqueDepenseService::class)->enregistrer($engagement, 'engagement_vise');

        $this->assertDatabaseHas('historique_depenses', [
            'engagement_id' => $engagement->id,
            'evenement' => 'engagement_vise',
        ]);
    }

    public function test_paiement_details_generes_a_la_validation(): void
    {
        [$engagement, , , $pay] = $this->chaineComplete(600);
        $pay->update(['statut' => 'brouillon', 'compteurs_appliques' => false]);

        $this->viserPaiement($pay);

        $this->assertTrue(PaiementDetail::where('paiement_id', $pay->id)->exists());
        $this->assertEqualsWithDelta(600.0, (float) PaiementDetail::where('paiement_id', $pay->id)->sum('montant'), 0.05);
    }

    public function test_rapprochement_bancaire_auto(): void
    {
        [, , , $pay] = $this->chaineComplete(400);
        $comptable = $this->user('comptable');
        $service = app(RapprochementBancaireService::class);

        $session = $service->creer($this->exercice, ['intitule' => 'Relevé test'], $comptable);
        $service->importerLignes($session, [[
            'date_operation' => now()->toDateString(),
            'libelle' => 'Virement',
            'reference_bancaire' => 'REF-D3-001',
            'montant' => 400,
        ]]);

        $matches = $service->rapprocherAuto($session->fresh());
        $this->assertSame(1, $matches);
        $this->assertSame($session->id, $pay->fresh()->rapprochement_bancaire_id);
    }

    public function test_cloture_rapprochement(): void
    {
        [, , , $pay] = $this->chaineComplete(350);
        $service = app(RapprochementBancaireService::class);
        $session = $service->creer($this->exercice, ['intitule' => 'Clôture test'], $this->user('comptable'));
        $ligne = $service->importerLignes($session, [[
            'date_operation' => now()->toDateString(),
            'libelle' => 'Virement',
            'montant' => 350,
        ]]);
        $this->assertSame(1, $ligne);

        $service->rapprocherAuto($session->fresh());
        $service->cloturer($session->fresh());
        $this->assertSame('clos', $session->fresh()->statut);
    }

    public function test_audit_detecte_engagement_sans_besoin(): void
    {
        Engagement::create([
            'numero' => 'ENG-SANS-BESOIN',
            'exercice_id' => $this->exercice->id,
            'type' => 'simple',
            'objet' => 'Direct',
            'montant_total' => 100,
            'statut' => 'brouillon',
            'cree_par' => $this->user('gestionnaire_budgetaire')->id,
        ]);

        $rapport = app(DepenseAuditService::class)->executer($this->exercice->id);
        $types = collect($rapport['anomalies'])->pluck('type');
        $this->assertTrue($types->contains('engagement_sans_besoin'));
    }

    public function test_audit_persiste_snapshot(): void
    {
        $this->chaineComplete();
        app(DepenseAuditService::class)->executer($this->exercice->id, $this->user('controleur_financier'), true);
        $this->assertTrue(AuditDepense::where('exercice_id', $this->exercice->id)->exists());
    }

    public function test_commande_depense_audit(): void
    {
        $this->artisan('depense:audit', ['--exercice' => 2026])
            ->expectsOutputToContain('Total anomalies')
            ->assertSuccessful();
    }

    public function test_journal_consolide_exportable(): void
    {
        $this->chaineComplete();
        $journal = app(JournalConsolideService::class)->journalConsolide($this->exercice);
        $this->assertGreaterThanOrEqual(3, $journal->count());
    }

    public function test_paiement_rejet_enregistre(): void
    {
        [, , , $pay] = $this->chaineComplete(200);
        $pay->update(['statut' => 'brouillon']);
        $this->workflow->demarrer($pay, 'PAIEMENT', $this->user('gestionnaire_budgetaire'));
        $this->workflow->rejeter($pay->fresh()->workflowActif, $this->user('comptable'), 'Compte erroné', 'financier');

        $this->assertSame('rejete', $pay->fresh()->statut);
        $this->assertTrue(HistoriqueDepense::where('entite_id', $pay->id)->where('evenement', 'paiement_rejete')->exists());
    }

    public function test_route_rapprochement_accessible(): void
    {
        $user = $this->user('comptable');
        $this->actingAs($user)->get(route('depense.rapprochement.index'))->assertOk();
    }
}
