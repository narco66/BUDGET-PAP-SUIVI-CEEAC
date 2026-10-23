<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
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

/** Phase D0 — contraintes métier, alias permissions, seeder chaîne dépense. */
class DepenseD0Test extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(DepensePermissionsSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_alias_permissions_depenses(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $lecteur = $this->user('lecteur');

        $this->assertTrue($gestionnaire->can('depenses.view'));
        $this->assertTrue($gestionnaire->can('depenses.engage'));
        $this->assertTrue($gestionnaire->can('depenses.liquidate'));
        $this->assertFalse($gestionnaire->can('depenses.admin'));

        $this->assertTrue($lecteur->can('depenses.view'));
        $this->assertFalse($lecteur->can('depenses.engage'));
    }

    public function test_depenses_admin_attribue_au_controleur_financier(): void
    {
        $this->assertTrue($this->user('controleur_financier')->can('depenses.admin'));
    }

    public function test_engagement_sans_besoin_refuse_pour_gestionnaire(): void
    {
        [, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D0-1', 1000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('besoin validé');

        app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'type' => 'simple', 'objet' => 'Direct'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 100]],
            $this->user('gestionnaire_budgetaire'),
        );
    }

    public function test_engagement_sans_besoin_autorise_pour_admin_depense(): void
    {
        [, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D0-2', 1000);

        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'type' => 'simple', 'objet' => 'Dérogation'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 100]],
            $this->user('controleur_financier'),
        );

        $this->assertInstanceOf(Engagement::class, $engagement);
    }

    public function test_engagement_avec_besoin_valide_et_activite(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D0-3', 1000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 500, 'EB-D0-3');

        $engagement = app(EngagementService::class)->creer(
            [
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'type' => 'simple',
                'objet' => 'Depuis besoin',
            ],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 500]],
            $user,
        );

        $this->assertSame($besoin->id, $engagement->expression_besoin_id);
        $this->assertSame($activite->id, $engagement->activite_id);
    }

    public function test_besoin_sans_activite_refuse_via_http(): void
    {
        $chef = $this->user('chef_service');

        $this->actingAs($chef)
            ->post(route('besoins.store'), [
                'exercice_id' => $this->exercice->id,
                'objet' => 'Sans activité',
                'montant_estime' => 1000,
            ])
            ->assertSessionHasErrors('activite_id');
    }

    public function test_engagement_http_sans_besoin_refuse(): void
    {
        [, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D0-4', 1000);

        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->post(route('engagements.store'), [
                'exercice_id' => $this->exercice->id,
                'type' => 'simple',
                'objet' => 'Sans besoin',
                'imputations' => [
                    ['ligne_budgetaire_id' => $ligne->id, 'montant' => 100],
                ],
            ])
            ->assertSessionHasErrors('expression_besoin_id');
    }

    public function test_create_engagement_sans_besoin_interdit_sauf_admin(): void
    {
        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->get(route('engagements.create'))
            ->assertForbidden();

        $this->actingAs($this->user('controleur_financier'))
            ->get(route('engagements.create'))
            ->assertOk();
    }

    public function test_chaine_depense_seeder_charge_sans_erreur(): void
    {
        $this->seed(\Database\Seeders\NomenclatureBudgetaireSeeder::class);
        $this->seed(\Database\Seeders\Pap2026Seeder::class);
        $this->seed(\Database\Seeders\Budget2026Seeder::class);

        $this->artisan('db:seed', ['--class' => 'ChaineDepenseSeeder'])->assertSuccessful();

        $this->assertTrue(ExpressionBesoin::where('numero', 'like', 'EB-2026-D%')->exists());
        $this->assertTrue(Engagement::where('numero', 'like', 'ENG-2026-D%')->exists());
    }
}
