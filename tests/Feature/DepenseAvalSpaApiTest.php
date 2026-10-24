<?php

namespace Tests\Feature;

use App\Models\Engagement;
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

class DepenseAvalSpaApiTest extends TestCase
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

    private function engagementVise(float $montant, string $codeLigne): array
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, $codeLigne, 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, $montant, 'EB-'.uniqid());
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Aval SPA'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => $montant]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);

        return [$gestionnaire, $engagement, $ligne];
    }

    public function test_lecteur_liste_sans_pouvoir_soumettre(): void
    {
        [, $engagement] = $this->engagementVise(200, 'LB-AVAL-LEC');
        Liquidation::create([
            'numero' => 'LIQ-LEC', 'engagement_id' => $engagement->id, 'montant' => 200,
            'statut' => 'brouillon', 'cree_par' => $engagement->cree_par,
        ]);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.depense.index', 'liquidation'))
            ->assertOk()
            ->assertJsonPath('data.0.montant', '200.00')
            ->assertJsonPath('data.0.actions.soumettre', false);

        $liq = Liquidation::first();
        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.depense.soumettre', ['liquidation', $liq->id]))
            ->assertForbidden();
    }

    public function test_parcours_api_liquidation_vers_paiement(): void
    {
        [$gestionnaire, $engagement, $ligne] = $this->engagementVise(750, 'LB-AVAL-E2E');
        $cf = $this->user('controleur_financier');
        $sg = $this->user('secretaire_general');
        $comptable = $this->user('comptable');

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant' => 750,
            'service_fait' => 'Prestation reçue',
        ])->assertCreated()->assertJsonPath('item.statut', 'brouillon');

        $liq = Liquidation::latest('id')->first();
        $chef = $this->user('chef_service');
        $this->actingAs($gestionnaire)->postJson(route('api.depense.soumettre', ['liquidation', $liq->id]))
            ->assertOk()->assertJsonPath('item.statut', 'soumis');
        $this->actingAs($chef)->postJson(route('api.depense.decisions', ['liquidation', $liq->id]), ['decision' => 'valider'])
            ->assertOk();
        $this->actingAs($cf)->postJson(route('api.depense.decisions', ['liquidation', $liq->id]), ['decision' => 'valider'])
            ->assertOk()->assertJsonPath('item.statut', 'valide');

        $ord = Ordonnancement::where('liquidation_id', $liq->id)->first();
        $this->assertNotNull($ord);
        $this->actingAs($gestionnaire)->postJson(route('api.depense.soumettre', ['ordonnancement', $ord->id]))->assertOk();
        $this->actingAs($sg)->postJson(route('api.depense.decisions', ['ordonnancement', $ord->id]), ['decision' => 'valider'])
            ->assertOk()->assertJsonPath('item.statut', 'valide');

        $pay = Paiement::where('ordonnancement_id', $ord->id)->first();
        $this->assertNotNull($pay);
        $this->assertSame('750.00', number_format((float) $pay->montant, 2, '.', ''));
        $this->actingAs($gestionnaire)->postJson(route('api.depense.soumettre', ['paiement', $pay->id]))->assertOk();
        $this->actingAs($comptable)->postJson(route('api.depense.decisions', ['paiement', $pay->id]), ['decision' => 'valider'])
            ->assertOk();
        $agent = $this->user('agent_comptable');
        $this->actingAs($agent)->postJson(route('api.depense.decisions', ['paiement', $pay->id]), ['decision' => 'valider'])
            ->assertOk()->assertJsonPath('item.statut', 'valide');

        $this->assertEqualsWithDelta(750.0, (float) $ligne->fresh()->montant_paye, 0.05);
    }

    public function test_soumission_idempotente_de_la_liquidation(): void
    {
        [$gestionnaire, $engagement] = $this->engagementVise(200, 'LB-AVAL-ID');
        $liq = Liquidation::create([
            'numero' => 'LIQ-ID', 'engagement_id' => $engagement->id, 'montant' => 200,
            'statut' => 'brouillon', 'cree_par' => $gestionnaire->id,
        ]);

        $headers = ['Idempotency-Key' => 'liq-soumettre-1'];
        $this->actingAs($gestionnaire)
            ->postJson(route('api.depense.soumettre', ['liquidation', $liq->id]), [], $headers)
            ->assertOk();
        $this->actingAs($gestionnaire)
            ->postJson(route('api.depense.soumettre', ['liquidation', $liq->id]), [], $headers)
            ->assertOk()
            ->assertJsonPath('code', 'soumis');

        $this->assertSame(1, $liq->fresh()->workflowInstances()->count());
    }

    public function test_quota_refuse_la_seconde_liquidation(): void
    {
        [$gestionnaire, $engagement] = $this->engagementVise(100, 'LB-AVAL-QT');

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant' => 80,
        ])->assertCreated();

        $this->actingAs($gestionnaire)->postJson(route('api.depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant' => 80,
        ])->assertStatus(422)->assertJsonPath('code', 'credits_ou_transition');

        $this->assertSame(1, Liquidation::count());
    }
}
