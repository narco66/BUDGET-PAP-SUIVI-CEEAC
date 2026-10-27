<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Services\EngagementService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

class DepenseSpaApiTest extends TestCase
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

    public function test_lecteur_liste_les_besoins_sans_pouvoir_soumettre(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-SPA-B', 5000);
        ExpressionBesoin::create([
            'numero' => 'EB-SPA-1',
            'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => $chef->id,
            'objet' => 'Besoin lecture',
            'montant_estime' => 100,
            'priorite' => 'moyenne',
            'statut' => 'brouillon',
        ]);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.besoins.index'))
            ->assertOk()
            ->assertJsonPath('data.0.montant_estime', '100.00')
            ->assertJsonPath('data.0.actions.soumettre', false);

        $besoin = ExpressionBesoin::first();
        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.besoins.soumettre', $besoin))
            ->assertForbidden();
    }

    public function test_parcours_api_besoin_vers_engagement_vise(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $commissaire = $this->user('commissaire');
        $cf = $this->user('controleur_financier');
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-SPA-E2E', 10_000);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id,
            'objet' => 'Fournitures E2E',
            'montant_estime' => 750,
            'priorite' => 'moyenne',
        ])->assertCreated()->assertJsonPath('besoin.statut', 'brouillon');

        $besoin = ExpressionBesoin::latest('id')->first();

        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))->assertOk()->assertJsonPath('besoin.statut', 'soumis');
        $this->actingAs($directeur)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])->assertOk();
        $this->actingAs($commissaire)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])
            ->assertOk()
            ->assertJsonPath('besoin.statut', 'valide');

        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());
        $this->actingAs($gestionnaire)->postJson(route('api.besoins.convertir', $besoin->fresh()))
            ->assertOk()
            ->assertJsonPath('besoin.statut', 'converti');
        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());

        $engagement = Engagement::where('expression_besoin_id', $besoin->id)->first();
        $this->assertNotNull($engagement);
        $this->assertSame('750.00', number_format((float) $engagement->montant_total, 2, '.', ''));
        // Soumission automatique de l'engagement à l'approbation de l'EB (chaine_depense.automatisation).
        $this->assertSame('soumis', $engagement->statut instanceof BackedEnum ? $engagement->statut->value : $engagement->statut);
        $this->assertTrue((bool) $engagement->credits_reserves);

        $this->actingAs($directeur)->postJson(route('api.engagements.decisions', $engagement->fresh()), [
            'decision' => 'valider',
        ])->assertOk();
        $this->actingAs($cf)->postJson(route('api.engagements.decisions', $engagement->fresh()), [
            'decision' => 'valider',
        ])->assertOk()->assertJsonPath('engagement.statut', 'vise');

        $this->assertSame(750.0, (float) $ligne->fresh()->montant_engage);
    }

    public function test_soumission_idempotente_de_l_engagement(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-SPA-ID', 5000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 200, 'EB-SPA-ID');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Idem'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 200]],
            $gestionnaire,
        );

        $headers = ['Idempotency-Key' => 'eng-soumettre-1'];
        $this->actingAs($gestionnaire)
            ->postJson(route('api.engagements.soumettre', $engagement), [], $headers)
            ->assertOk();
        $this->actingAs($gestionnaire)
            ->postJson(route('api.engagements.soumettre', $engagement), [], $headers)
            ->assertOk()
            ->assertJsonPath('code', 'soumis');

        $this->assertSame(200.0, (float) $ligne->fresh()->montant_engage);
    }

    public function test_controle_de_credits_refuse_le_second_engagement(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-SPA-CR', 100);
        $besoinA = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 80, 'EB-SPA-A');
        $besoinB = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 80, 'EB-SPA-B');

        $engA = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoinA->id, 'type' => 'simple', 'objet' => 'A'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 80]],
            $gestionnaire,
        );
        $engB = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoinB->id, 'type' => 'simple', 'objet' => 'B'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 80]],
            $gestionnaire,
        );

        $this->actingAs($gestionnaire)->postJson(route('api.engagements.soumettre', $engA))->assertOk();
        $this->actingAs($gestionnaire)->postJson(route('api.engagements.soumettre', $engB))
            ->assertStatus(422)
            ->assertJsonPath('code', 'credits_ou_transition');

        $this->assertSame(80.0, (float) $ligne->fresh()->montant_engage);
        $this->assertFalse((bool) $engB->fresh()->credits_reserves);
    }

    public function test_options_et_creation_spa_du_besoin(): void
    {
        $chef = $this->user('chef_service');
        [$activite] = $this->activiteEtLigne($this->exercice, 'LB-SPA-OPT', 3000);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.besoins.options', ['exercice' => $this->exercice->id]))
            ->assertForbidden();

        $this->actingAs($chef)
            ->getJson(route('api.besoins.options', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('activites.0.id', $activite->id);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id,
            'objet' => 'Besoin SPA création',
            'montant_estime' => 120,
            'priorite' => 'haute',
        ])->assertCreated()->assertJsonPath('besoin.statut', 'brouillon')
            ->assertJsonPath('besoin.montant_estime', '120.00');
    }
}
