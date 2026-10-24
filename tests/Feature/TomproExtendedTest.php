<?php

namespace Tests\Feature;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\EcritureComptable;
use App\Models\Encaissement;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Recette;
use App\Models\User;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** P2-8 — connecteur TOMPRO étendu (encaissements, synthèse, référentiel). */
class TomproExtendedTest extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private Exercice $exercice;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            InteroperabiliteApiPermissionsSeeder::class,
            DepensePermissionsSeeder::class,
            WorkflowSeeder::class,
        ]);
        $this->workflow = app(WorkflowService::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_referentiel_et_synthese_accessibles(): void
    {
        Sanctum::actingAs($this->user('controleur_financier'), ['tompro']);

        $this->getJson(route('api.tompro.referentiel'))
            ->assertOk()
            ->assertJsonPath('version', '1.1')
            ->assertJsonStructure(['endpoints', 'plan_comptable']);

        $this->getJson(route('api.tompro.synthese', ['exercice_id' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonStructure(['synthese' => ['paiements_valides', 'encaissements_comptabilises', 'ecritures_a_exporter']]);
    }

    public function test_encaissements_exportes_avec_ecritures_produit(): void
    {
        $titreR = BudgetTitre::create(['sens' => 'recette', 'code' => '7', 'intitule' => 'Recettes']);
        $artR = BudgetArticle::create(['titre_id' => $titreR->id, 'code' => '72', 'intitule' => 'Contrib']);
        $parR = BudgetParagraphe::create(['article_id' => $artR->id, 'code' => '721', 'intitule' => 'EM']);
        $rubR = BudgetRubrique::create(['paragraphe_id' => $parR->id, 'code' => '721010', 'intitule' => 'C']);

        $recette = Recette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'REC-TP-1',
            'rubrique_id' => $rubR->id,
            'montant_prevu' => 5_000,
            'montant_realise' => 5_000,
            'statut' => 'realisee',
        ]);

        Encaissement::create([
            'recette_id' => $recette->id,
            'reference' => 'ENC-TP-1',
            'montant' => 2_500,
            'mode_paiement' => 'virement',
            'date_encaissement' => '2026-05-10',
            'statut' => 'valide',
            'valide_le' => now(),
        ]);

        Sanctum::actingAs($this->user('controleur_financier'), ['tompro']);

        $response = $this->getJson(route('api.tompro.encaissements', [
            'exercice_id' => $this->exercice->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('nombre', 1)
            ->assertJsonStructure(['encaissements']);

        $ligne = $response->json('encaissements.0');
        $this->assertSame('encaissement', $ligne['type']);
        $this->assertSame(2_500, $ligne['montant_xaf']);
        $this->assertNotEmpty($ligne['ecritures_syscohada']);
        $this->assertSame(EcritureComptable::COMPTES['banque'], $ligne['ecritures_syscohada'][0]['debit']);
        $this->assertStringStartsWith('7', (string) $ligne['ecritures_syscohada'][0]['credit']);
    }

    public function test_filtre_non_exportes_sur_paiements(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-TP-FIL', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 200, 'EB-TP-FIL');

        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Filtre export'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 200]],
            $user,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);

        $liq = Liquidation::create(['numero' => 'LIQ-TP-F', 'engagement_id' => $engagement->id, 'montant' => 200, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-TP-F', 'liquidation_id' => $liq->id, 'montant' => 200, 'statut' => 'valide', 'compteurs_appliques' => true]);

        $pay = Paiement::create([
            'numero' => 'PAY-TP-F', 'ordonnancement_id' => $ord->id, 'montant' => 200,
            'montant_xaf' => 200, 'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $user->id,
        ]);
        $this->viserPaiement($pay);

        Sanctum::actingAs($user, ['tompro']);

        $this->getJson(route('api.tompro.paiements', [
            'exercice_id' => $this->exercice->id,
            'non_exportes' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('nombre', 1);

        $this->getJson(route('api.tompro.paiements', [
            'exercice_id' => $this->exercice->id,
            'marquer_export' => 1,
        ]))->assertOk();

        $this->getJson(route('api.tompro.paiements', [
            'exercice_id' => $this->exercice->id,
            'non_exportes' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('nombre', 0);
    }

    public function test_nouveaux_endpoints_exigent_scope_tompro(): void
    {
        Sanctum::actingAs($this->user('controleur_financier'), ['lecture']);

        $this->getJson(route('api.tompro.encaissements'))->assertForbidden();
        $this->getJson(route('api.tompro.referentiel'))->assertForbidden();
    }
}
