<?php

namespace Tests\Feature;

use App\Models\EcritureComptable;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Depense\DevisePaiementService;
use App\Services\Depense\EcritureComptableService;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Phase D4 — multi-devises et écritures comptables SYSCOHADA. */
class DepenseD4Test extends TestCase
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

    public function test_paiement_multi_devise_calcule_montant_xaf(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D4-DEV', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 100, 'EB-D4-DEV');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Devise'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 100]],
            $user,
        );
        $engagement->update(['statut' => 'vise']);
        $liq = Liquidation::create(['numero' => 'LIQ-D4-D', 'engagement_id' => $engagement->id, 'montant' => 100, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-D4-D', 'liquidation_id' => $liq->id, 'montant' => 100, 'statut' => 'valide', 'compteurs_appliques' => true]);

        $pay = Paiement::create([
            'numero' => 'PAY-D4-EUR', 'ordonnancement_id' => $ord->id, 'montant' => 1000,
            'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $user->id,
        ]);

        app(DevisePaiementService::class)->appliquer($pay, [
            'montant' => 1000,
            'devise' => 'EUR',
            'taux_change' => 655.957,
        ]);

        $pay->refresh();
        $this->assertSame('EUR', $pay->devise);
        $this->assertEqualsWithDelta(655_957.0, (float) $pay->montant_xaf, 0.01);
    }

    public function test_compteurs_utilisent_montant_xaf(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D4-FX', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 100, 'EB-D4-FX');

        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'FX test'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 100]],
            $user,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);

        $liq = Liquidation::create(['numero' => 'LIQ-D4-FX', 'engagement_id' => $engagement->id, 'montant' => 100, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-D4-FX', 'liquidation_id' => $liq->id, 'montant' => 100, 'statut' => 'valide', 'compteurs_appliques' => true]);

        $pay = Paiement::create([
            'numero' => 'PAY-D4-FX', 'ordonnancement_id' => $ord->id, 'montant' => 10,
            'devise' => 'EUR', 'taux_change' => 655.957, 'montant_xaf' => 6559.57,
            'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $user->id,
        ]);

        $this->workflow->demarrer($pay, 'PAIEMENT', $user);
        $this->viserPaiement($pay);

        $this->assertEqualsWithDelta(6559.57, (float) $ligne->fresh()->montant_paye, 0.05);
    }

    public function test_ecritures_comptables_generees_a_la_validation(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D4-EC', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 200, 'EB-D4-EC');

        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Ecritures test'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 200]],
            $user,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);

        $liq = Liquidation::create(['numero' => 'LIQ-D4-EC', 'engagement_id' => $engagement->id, 'montant' => 200, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-D4-EC', 'liquidation_id' => $liq->id, 'montant' => 200, 'statut' => 'valide', 'compteurs_appliques' => true]);

        $pay = Paiement::create([
            'numero' => 'PAY-D4-EC', 'ordonnancement_id' => $ord->id, 'montant' => 200,
            'devise' => 'XAF', 'taux_change' => 1, 'montant_xaf' => 200,
            'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $user->id,
        ]);

        $this->workflow->demarrer($pay, 'PAIEMENT', $user);
        $this->viserPaiement($pay);

        $ecritures = EcritureComptable::where('paiement_id', $pay->id)->get();
        $this->assertNotEmpty($ecritures);
        $this->assertSame(EcritureComptable::COMPTES['banque'], $ecritures->first()->compte_credit);
        $this->assertEqualsWithDelta(200.0, (float) $ecritures->sum('montant'), 0.05);
    }

    /** Régression : la numérotation lisait une colonne `numero` inexistante (masqué par SQLite, échec MySQL/PostgreSQL). */
    public function test_numerotation_ecritures_sur_numero_piece(): void
    {
        $annee = $this->exercice->annee;
        EcritureComptable::create([
            'exercice_id' => $this->exercice->id, 'journal' => 'BQ', 'numero_piece' => "EC-{$annee}-0001",
            'date_ecriture' => now(), 'compte_debit' => '401', 'compte_credit' => '521', 'montant' => 10, 'devise' => 'XAF',
            'libelle' => 'Écriture existante', 'statut' => 'brouillon',
        ]);

        $this->assertSame("EC-{$annee}-0002", \App\Support\Numero::suivant('ecritures_comptables', 'EC', $annee, 'numero_piece'));
        $this->assertStringContainsString(
            "'numero_piece')",
            file_get_contents(app_path('Services/Depense/EcritureComptableService.php'))
        );
    }

    public function test_tompro_inclut_ecritures_syscohada(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D4-TP', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 150, 'EB-D4-TP');

        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'TomPro test'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 150]],
            $user,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);

        $liq = Liquidation::create(['numero' => 'LIQ-D4-TP', 'engagement_id' => $engagement->id, 'montant' => 150, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-D4-TP', 'liquidation_id' => $liq->id, 'montant' => 150, 'statut' => 'valide', 'compteurs_appliques' => true]);

        $pay = Paiement::create([
            'numero' => 'PAY-D4-TP', 'ordonnancement_id' => $ord->id, 'montant' => 150,
            'devise' => 'XAF', 'taux_change' => 1, 'montant_xaf' => 150,
            'mode_paiement' => 'virement', 'statut' => 'brouillon', 'cree_par' => $user->id,
        ]);

        $this->workflow->demarrer($pay, 'PAIEMENT', $user);
        $this->viserPaiement($pay);

        Sanctum::actingAs($user, ['tompro']);
        $response = $this->getJson(route('api.tompro.paiements'));
        $response->assertOk();
        $ligne = $response->json('ecritures.0');
        $this->assertSame(150, $ligne['montant_xaf']);
        $this->assertNotEmpty($ligne['ecritures_syscohada']);
        $this->assertStringStartsWith('6', (string) $ligne['ecritures_syscohada'][0]['debit']);
    }

    public function test_export_ecritures_route(): void
    {
        $user = $this->user('administrateur');

        $this->actingAs($user)
            ->get(route('depense.export.ecritures', ['exercice' => 2026]))
            ->assertOk();
    }
}
