<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\RapprochementBancaire;
use App\Models\User;
use App\Services\EngagementService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

class RapprochementSpaApiTest extends TestCase
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

    private function paiementValide(float $montant, string $reference): Paiement
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-RB-'.uniqid(), 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, $montant, 'EB-RB-'.uniqid());
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'RB'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => $montant]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create([
            'numero' => 'LIQ-RB-'.uniqid(), 'engagement_id' => $engagement->id, 'montant' => $montant,
            'statut' => 'valide', 'cree_par' => $gestionnaire->id, 'compteurs_appliques' => true,
        ]);
        $ord = Ordonnancement::create([
            'numero' => 'ORD-RB-'.uniqid(), 'liquidation_id' => $liq->id, 'montant' => $montant,
            'statut' => 'valide', 'cree_par' => $gestionnaire->id, 'compteurs_appliques' => true,
        ]);

        return Paiement::create([
            'numero' => 'PAY-RB-'.uniqid(), 'ordonnancement_id' => $ord->id, 'montant' => $montant,
            'date_paiement' => now(), 'mode_paiement' => 'virement', 'reference_bancaire' => $reference,
            'statut' => 'valide', 'cree_par' => $gestionnaire->id, 'compteurs_appliques' => true,
        ]);
    }

    public function test_lecteur_consulte_sans_pouvoir_creer(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.rapprochements.index'))
            ->assertOk();

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.rapprochements.store'), [
                'exercice_id' => $this->exercice->id,
                'intitule' => 'Interdit',
            ])
            ->assertForbidden();
    }

    public function test_parcours_api_import_auto_cloture(): void
    {
        $pay = $this->paiementValide(400, 'REF-SPA-RB');
        $comptable = $this->user('comptable');

        $this->actingAs($comptable)->postJson(route('api.rapprochements.store'), [
            'exercice_id' => $this->exercice->id,
            'intitule' => 'Relevé SPA',
        ])->assertCreated()->assertJsonPath('session.statut', 'brouillon');

        $session = RapprochementBancaire::latest('id')->first();
        $csv = now()->toDateString().';Virement;REF-SPA-RB;400.00';

        $this->actingAs($comptable)->postJson(route('api.rapprochements.importer', $session), [
            'lignes' => $csv,
        ])->assertOk()->assertJsonPath('importees', 1)
            ->assertJsonPath('session.montant_releve', '400.00');

        $this->actingAs($comptable)->postJson(route('api.rapprochements.auto', $session))
            ->assertOk()
            ->assertJsonPath('matches', 1);

        $this->assertSame($session->id, $pay->fresh()->rapprochement_bancaire_id);

        $this->actingAs($comptable)->postJson(route('api.rapprochements.cloturer', $session->fresh()))
            ->assertOk()
            ->assertJsonPath('session.statut', 'clos');
    }

    public function test_rapprochement_auto_idempotent(): void
    {
        $this->paiementValide(250, 'REF-IDEM');
        $comptable = $this->user('comptable');
        $session = app(\App\Services\Depense\RapprochementBancaireService::class)->creer(
            $this->exercice,
            ['intitule' => 'Idem'],
            $comptable,
        );
        app(\App\Services\Depense\RapprochementBancaireService::class)->importerLignes($session, [[
            'date_operation' => now()->toDateString(),
            'libelle' => 'Virement',
            'reference_bancaire' => 'REF-IDEM',
            'montant' => 250,
        ]]);

        $headers = ['Idempotency-Key' => 'rb-auto-1'];
        $this->actingAs($comptable)
            ->postJson(route('api.rapprochements.auto', $session), [], $headers)
            ->assertOk()
            ->assertJsonPath('matches', 1);
        $this->actingAs($comptable)
            ->postJson(route('api.rapprochements.auto', $session), [], $headers)
            ->assertOk()
            ->assertJsonPath('matches', 1);
    }
}
