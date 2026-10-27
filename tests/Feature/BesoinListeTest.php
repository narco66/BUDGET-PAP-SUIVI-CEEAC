<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\ExpressionBesoin;
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

/** Liste des expressions de besoin : indicateurs, filtres, avancement dans la chaîne, action attendue. */
class BesoinListeTest extends TestCase
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

    private function besoin(User $demandeur, string $statut, float $montant, string $objet, string $priorite = 'moyenne', string $categorie = 'pap'): ExpressionBesoin
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-BL-'.uniqid(), 10_000_000);

        return ExpressionBesoin::create([
            'numero' => 'EB-BL-'.substr(uniqid(), -6), 'exercice_id' => $this->exercice->id, 'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id, 'demandeur_id' => $demandeur->id, 'objet' => $objet,
            'montant_estime' => $montant, 'priorite' => $priorite, 'statut' => $statut, 'categorie' => $categorie,
        ]);
    }

    public function test_indicateurs_avancement_et_action_attendue(): void
    {
        $chef = $this->user('chef_service');
        $pointFocal = $this->user('point_focal');
        $brouillon = $this->besoin($pointFocal, 'brouillon', 50_000, 'Fournitures de bureau');
        $valide = $this->besoin($chef, 'valide', 800_000, 'Maintenance véhicules');
        $eng = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $valide->id, 'type' => 'simple', 'objet' => 'Maintenance', 'beneficiaire' => 'GARAGE OMEGA'],
            [['ligne_budgetaire_id' => $valide->ligne_budgetaire_id, 'montant' => 800_000]], $chef);
        $liq = Liquidation::create(['numero' => 'LIQ-BL', 'engagement_id' => $eng->id, 'montant' => 800_000, 'statut' => 'valide']);
        $ord = Ordonnancement::create(['numero' => 'ORD-BL', 'liquidation_id' => $liq->id, 'montant' => 800_000, 'statut' => 'valide']);
        Paiement::create(['numero' => 'PAY-BL', 'ordonnancement_id' => $ord->id, 'montant' => 300_000, 'devise' => 'XAF', 'statut' => 'valide']);

        $page = $this->actingAs($chef)->get(route('besoins.index'))->assertOk()
            ->assertSee($brouillon->numero)->assertSee($valide->numero)->assertSee($eng->numero)->assertSee('payé 300 000')
            ->assertSee('Vous');   // EB préparée par le point focal : à soumettre par le chef de service
        $this->assertSame('PAY', $page->viewData('avancement')[$valide->id]['etape']);
        $this->assertSame(2, $page->viewData('total')['n']);
        $this->assertGreaterThanOrEqual(1, $page->viewData('enAttenteMoi'));
    }

    public function test_filtres_recherche_statut_categorie_mes_besoins_et_tri(): void
    {
        $chef = $this->user('chef_service');
        $autre = $this->user('chef_service');
        $a = $this->besoin($chef, 'brouillon', 50_000, 'Fournitures de bureau', 'basse');
        $b = $this->besoin($autre, 'valide', 900_000, 'Billets d\'avion mission', 'haute', 'hors_pap');
        $admin = $this->user('administrateur');

        $this->actingAs($admin)->get(route('besoins.index', ['q' => 'avion']))->assertSee($b->numero)->assertDontSee($a->numero);
        $page = $this->actingAs($admin)->get(route('besoins.index', ['statut' => 'brouillon']))->assertSee($a->numero)->assertDontSee($b->numero);
        $this->assertSame(2, $page->viewData('total')['n'], 'Indicateurs hors filtre de statut.');
        $this->actingAs($admin)->get(route('besoins.index', ['categorie' => 'hors_pap']))->assertSee($b->numero)->assertDontSee($a->numero);
        $this->actingAs($chef)->get(route('besoins.index', ['mes' => 1]))->assertSee($a->numero)->assertDontSee($b->numero);
        $this->assertSame($b->id, $this->actingAs($admin)->get(route('besoins.index', ['tri' => 'priorite']))->viewData('besoins')->first()->id);
        $this->actingAs($admin)->get(route('besoins.index', ['q' => 'introuvable']))->assertSee('Aucun résultat pour ces filtres');
    }

    public function test_la_liste_ne_charge_plus_les_referentiels_du_formulaire(): void
    {
        $this->actingAs($this->user('administrateur'))->get(route('besoins.index'))->assertOk()
            ->assertViewMissing('imputations')->assertViewMissing('activites')->assertViewMissing('responsables');
    }

    public function test_acces_refuse_sans_permission(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true, 'statut' => 'actif']))->get(route('besoins.index'))->assertForbidden();
    }
}
