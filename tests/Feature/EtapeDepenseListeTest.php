<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\EngagementService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Listes LIQ / ORD / PAY : indicateurs, filtres, origine, suite financière, action attendue, cloisonnement. */
class EtapeDepenseListeTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'notifications.email.enabled' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function ordonnancement(float $montant, string $statut, string $beneficiaire, User $gestionnaire): Ordonnancement
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-L-'.uniqid(), $montant * 2);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, $montant, 'EB-L-'.substr(uniqid(), -6));
        $eng = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Achat', 'beneficiaire' => $beneficiaire],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => $montant]], $gestionnaire);
        $eng->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-'.substr(uniqid(), -6), 'engagement_id' => $eng->id, 'montant' => $montant, 'statut' => 'valide', 'compteurs_appliques' => true]);

        return Ordonnancement::create(['numero' => 'ORD-'.substr(uniqid(), -6), 'liquidation_id' => $liq->id, 'montant' => $montant, 'statut' => $statut, 'cree_par' => $gestionnaire->id]);
    }

    public function test_liste_ordonnancements_indicateurs_origine_suite_et_action_attendue(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $brouillon = $this->ordonnancement(300_000, 'brouillon', 'SOCIETE ALPHA', $gestionnaire);
        $signe = $this->ordonnancement(1_000_000, 'valide', 'SOCIETE BETA', $gestionnaire);
        Paiement::create(['numero' => 'PAY-L1', 'ordonnancement_id' => $signe->id, 'montant' => 400_000, 'devise' => 'XAF', 'statut' => 'valide']);

        $page = $this->actingAs($gestionnaire)->get(route('depense.index', 'ordonnancement'))->assertOk();
        $page->assertSee($brouillon->numero)->assertSee($signe->numero)
            ->assertSee('SOCIETE ALPHA')->assertSee($brouillon->liquidation->engagement->numero)
            ->assertSee('Reste 600 000')
            ->assertSee('Vous');   // ORD brouillon : tâche de soumission du gestionnaire
        $this->assertSame(1, $page->viewData('enAttenteMoi'));
        $this->assertSame(2, $page->viewData('total')['n']);
        $this->assertEquals(1_300_000, $page->viewData('total')['montant']);
    }

    public function test_filtres_recherche_statut_et_tri(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $a = $this->ordonnancement(300_000, 'brouillon', 'SOCIETE ALPHA', $gestionnaire);
        $b = $this->ordonnancement(900_000, 'valide', 'SOCIETE BETA', $gestionnaire);

        $this->actingAs($gestionnaire)->get(route('depense.index', ['ordonnancement', 'q' => 'beta']))
            ->assertOk()->assertSee($b->numero)->assertDontSee($a->numero);
        $page = $this->actingAs($gestionnaire)->get(route('depense.index', ['ordonnancement', 'statut' => 'brouillon']))
            ->assertOk()->assertSee($a->numero)->assertDontSee($b->numero);
        $this->assertSame(2, $page->viewData('total')['n'], 'Les indicateurs ignorent le filtre de statut (restent cliquables).');
        $tri = $this->actingAs($gestionnaire)->get(route('depense.index', ['ordonnancement', 'tri' => 'montant_desc']))->viewData('items');
        $this->assertSame($b->id, $tri->first()->id);
        $this->actingAs($gestionnaire)->get(route('depense.index', ['ordonnancement', 'q' => 'inexistant']))->assertSee('Aucun résultat pour ces filtres');
    }

    public function test_listes_liquidations_et_paiements_s_affichent(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $ord = $this->ordonnancement(500_000, 'valide', 'SOCIETE GAMMA', $gestionnaire);
        Paiement::create(['numero' => 'PAY-L2', 'ordonnancement_id' => $ord->id, 'montant' => 500_000, 'devise' => 'XAF', 'statut' => 'valide', 'reference_bancaire' => 'VIR-REF-77']);
        $admin = $this->user('administrateur');

        $this->actingAs($admin)->get(route('depense.index', 'liquidation'))->assertOk()->assertSee('SOCIETE GAMMA')->assertSee('Soldé');
        $this->actingAs($admin)->get(route('depense.index', ['paiement', 'q' => 'VIR-REF']))->assertOk()->assertSee('PAY-L2')->assertSee('VIR-REF-77');
    }

    public function test_liste_engagements_indicateurs_liquide_filtres(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $ord = $this->ordonnancement(800_000, 'valide', 'SOCIETE EPSILON', $gestionnaire);
        $autre = $this->ordonnancement(200_000, 'brouillon', 'SOCIETE ZETA', $gestionnaire);
        $admin = $this->user('administrateur');

        $page = $this->actingAs($admin)->get(route('engagements.index'))->assertOk()
            ->assertSee('SOCIETE EPSILON')->assertSee($ord->liquidation->engagement->numero)->assertSee('Entièrement liquidé');
        $this->assertSame(2, $page->viewData('total')['n']);
        $this->assertEquals(1_000_000, $page->viewData('total')['montant']);

        $this->actingAs($admin)->get(route('engagements.index', ['q' => 'zeta']))
            ->assertSee($autre->liquidation->engagement->numero)->assertDontSee($ord->liquidation->engagement->numero);
        $this->actingAs($admin)->get(route('engagements.index', ['type_engagement' => 'marche']))->assertSee('Aucun résultat pour ces filtres');
        $this->assertSame($ord->liquidation->engagement->id,
            $this->actingAs($admin)->get(route('engagements.index', ['tri' => 'montant_desc']))->viewData('items')->first()->id);
    }

    public function test_acces_refuse_sans_permission(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true, 'statut' => 'actif']))
            ->get(route('depense.index', 'ordonnancement'))->assertForbidden();
    }

    public function test_formulaire_de_creation_d_ordonnancement_s_ouvre(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $ord = $this->ordonnancement(200_000, 'valide', 'SOCIETE DELTA', $gestionnaire);

        $this->actingAs($this->user('administrateur'))->get(route('depense.create', ['paiement', 'parent' => $ord->id]))->assertOk();
    }
}
