<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\ReservationCredit;
use App\Models\Structure;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\Depense\DepenseReportingService;
use App\Services\EngagementService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Corrections issues de l'audit global des données (25/10/2026). */
class AuditDonneesCorrectionsTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'notifications.email.enabled' => false, 'chaine_depense.automatisation.demarrage_liquidation' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert', 'est_actif' => true, 'devise' => 'XAF']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function engagementVise(User $user, float $montant, string $suffixe, ?int $structureId = null): Engagement
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-AUD-'.$suffixe, $montant * 3);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, $montant, 'EB-AUD-'.$suffixe);
        if ($structureId) {
            $besoin->update(['structure_id' => $structureId]);
        }
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Audit '.$suffixe],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => $montant]],
            $user,
        );
        $engagement->reserverCredits();
        $engagement->update(['statut' => 'vise']);

        return $engagement->fresh();
    }

    public function test_degagement_partiel_reduit_aussi_la_reservation_active(): void
    {
        $user = $this->user('gestionnaire_budgetaire');
        $engagement = $this->engagementVise($user, 2_000_000, 'DEG');

        app(EngagementService::class)->degager($engagement, 800_000, $user);

        $this->assertSame(1_200_000.0, (float) $engagement->fresh()->imputations()->sum('montant'));
        $this->assertSame(1_200_000.0, (float) ReservationCredit::where('engagement_id', $engagement->id)->where('statut', 'active')->sum('montant'));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('depense:reconcilier-reservations'));
        $this->assertStringContainsString('Écarts détectés : 0', \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_paiement_a_instrument_rejete_non_compte_comme_regle(): void
    {
        $user = $this->user('gestionnaire_budgetaire');
        $engagement = $this->engagementVise($user, 1_000_000, 'REJ');
        $liq = Liquidation::create(['numero' => 'LIQ-AUD-REJ', 'engagement_id' => $engagement->id, 'montant' => 1_000_000, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-AUD-REJ', 'liquidation_id' => $liq->id, 'montant' => 1_000_000, 'statut' => 'valide', 'cree_par' => $user->id]);
        Paiement::create(['numero' => 'PAY-AUD-1', 'ordonnancement_id' => $ord->id, 'montant' => 400_000, 'devise' => 'XAF', 'statut' => 'valide', 'cree_par' => $user->id]);
        Paiement::create(['numero' => 'PAY-AUD-2', 'ordonnancement_id' => $ord->id, 'montant' => 600_000, 'devise' => 'XAF', 'statut' => 'valide', 'etat_execution' => 'instrument_rejete', 'cree_par' => $user->id]);

        $this->assertSame(400_000.0, (float) Paiement::regles()->sum('montant'));
    }

    public function test_execution_par_tache_sans_multiplication_par_les_paiements_partiels(): void
    {
        $user = $this->user('gestionnaire_budgetaire');
        $engagement = $this->engagementVise($user, 900_000, 'TAC');
        $tache = $this->tachePourActivite(\App\Models\Activite::first(), 'T-AUD');
        $engagement->update(['tache_id' => $tache->id]);
        $liq = Liquidation::create(['numero' => 'LIQ-AUD-TAC', 'engagement_id' => $engagement->id, 'montant' => 900_000, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-AUD-TAC', 'liquidation_id' => $liq->id, 'montant' => 900_000, 'statut' => 'valide', 'cree_par' => $user->id]);
        foreach ([1 => 300_000, 2 => 300_000, 3 => 300_000] as $i => $m) {
            Paiement::create(['numero' => "PAY-AUD-T{$i}", 'ordonnancement_id' => $ord->id, 'montant' => $m, 'devise' => 'XAF', 'statut' => 'valide', 'cree_par' => $user->id]);
        }

        $ligne = app(DepenseReportingService::class)->executionParTache($this->exercice->id)->firstWhere('tache_id', $tache->id);
        $this->assertSame(900_000.0, $ligne->montant_liquide, 'Liquidé compté une fois, malgré trois paiements partiels.');
        $this->assertSame(900_000.0, $ligne->montant_ordonnance);
        $this->assertSame(900_000.0, $ligne->montant_paye);
    }

    public function test_listes_de_la_depense_respectent_le_perimetre_organique_defini(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $mienne = Structure::create(['code' => 'DIR-A', 'libelle' => 'Direction A', 'type' => 'direction', 'actif' => true]);
        $autre = Structure::create(['code' => 'DIR-B', 'libelle' => 'Direction B', 'type' => 'direction', 'actif' => true]);
        $visible = $this->engagementVise($gestionnaire, 100_000, 'PA', $mienne->id);
        $cache = $this->engagementVise($gestionnaire, 100_000, 'PB', $autre->id);

        $directeur = $this->user('directeur');
        $directeur->accessScopes()->create(['type' => 'direction', 'scope_id' => $mienne->id]);
        $scopes = app(AccessScopeService::class);

        $ids = $scopes->filtrerDossiersDepense(Engagement::query(), $directeur)->pluck('id')->all();
        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($cache->id, $ids, 'Engagement d\'une autre direction masqué.');
        // Un accès global (aucun périmètre défini) reste inchangé.
        $this->assertCount(2, $scopes->filtrerDossiersDepense(Engagement::query(), $gestionnaire)->get());
    }

    public function test_en_tete_affiche_l_exercice_actif_lu_en_base(): void
    {
        $this->exercice->update(['est_actif' => false]);
        Exercice::create(['annee' => 2027, 'libelle' => 'Ex 2027', 'statut' => 'ouvert', 'est_actif' => true, 'devise' => 'XAF']);
        \Illuminate\Support\Once::flush();

        $this->actingAs($this->user('gestionnaire_budgetaire'))->get(route('dashboard'))
            ->assertOk()->assertSee('Exercice 2027 · XAF');
    }

    public function test_enveloppe_pap_partagee_non_signalee_comme_desynchronisee(): void
    {
        [$a1, $enveloppe] = $this->activiteEtLigne($this->exercice, 'ACT-2011', 1_000_000);
        $a2 = \App\Models\Activite::query()->create(array_merge($a1->only(['sous_produit_id', 'produit_id', 'structure_id']), ['code' => 'ACT-B', 'intitule' => 'Deuxième activité', 'montant_total' => 1000]));
        $a1->update(['ligne_budgetaire_id' => $enveloppe->id]);
        $a2->update(['ligne_budgetaire_id' => $enveloppe->id]);
        $enveloppe->update(['activite_id' => null]);

        $this->assertSame(0, app(\App\Services\Budget\StructureBudgetCoherenceService::class)->compterLiaisonsDesynchronisees($this->exercice->id));
        app(\App\Services\Budget\RbmBudgetSyncService::class)->synchroniserActivite($a2->fresh());
        $this->assertNull($enveloppe->fresh()->activite_id, 'La réconciliation n\'attribue pas l\'enveloppe partagée à une seule activité.');
    }
}
