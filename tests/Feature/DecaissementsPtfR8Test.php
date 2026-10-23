<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\ConventionFinancement;
use App\Models\DecaissementConvention;
use App\Models\PartenaireFinancier;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Recettes\DecaissementConventionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** Phase R8 — décaissements PTF, taux de mobilisation, reliquat, liaison activité PAP. */
class DecaissementsPtfR8Test extends TestCase
{
    use RefreshDatabase;

    private ConventionFinancement $convention;
    private Activite $activite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $partenaire = PartenaireFinancier::create(['code' => 'BAD', 'libelle' => 'Banque Africaine de Développement', 'actif' => true]);
        $this->convention = ConventionFinancement::create([
            'partenaire_id' => $partenaire->id,
            'code' => 'CONV-BAD-1',
            'libelle' => 'Appui institutionnel',
            'montant_accord' => 20_000_000,
            'statut' => 'actif',
            'actif' => true,
        ]);

        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P1', 'intitule' => 'Pilier 1', 'ordre' => 1]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe 1', 'ordre' => 1]);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit 1', 'ordre' => 1]);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'Sous-produit 1', 'ordre' => 1]);
        $this->activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $produit->id,
            'code' => 'ACT1', 'intitule' => 'Activité financée PTF', 'ordre' => 1,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_table_existe(): void
    {
        $this->assertTrue(Schema::hasTable('decaissements_convention'));
    }

    public function test_decaissement_met_a_jour_decaisse_reliquat_et_taux(): void
    {
        app(DecaissementConventionService::class)->enregistrer($this->convention, [
            'reference' => 'DEC-1', 'objet' => 'Tranche 1',
            'montant' => 5_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'date_decaissement' => '2026-03-01', 'statut' => 'execute',
            'activite_id' => $this->activite->id,
        ], $this->user('gestionnaire_budgetaire'));

        $this->convention->refresh();
        $this->assertEquals(5_000_000.0, (float) $this->convention->montant_decaisse);
        $this->assertEquals(15_000_000.0, (float) $this->convention->reliquat);
        $this->assertEquals(25.0, $this->convention->taux_mobilisation);
    }

    public function test_decaissement_ne_depasse_pas_l_accord(): void
    {
        $service = app(DecaissementConventionService::class);

        $this->expectException(RuntimeException::class);
        $service->enregistrer($this->convention, [
            'reference' => 'DEC-OVER', 'objet' => 'Trop',
            'montant' => 25_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'date_decaissement' => '2026-03-01', 'statut' => 'execute',
        ], $this->user('gestionnaire_budgetaire'));
    }

    public function test_annulation_restitue_le_reliquat(): void
    {
        $service = app(DecaissementConventionService::class);
        $dec = $service->enregistrer($this->convention, [
            'reference' => 'DEC-2', 'objet' => 'Tranche', 'montant' => 8_000_000,
            'devise' => 'XAF', 'taux_change' => 1, 'date_decaissement' => '2026-03-01', 'statut' => 'execute',
        ], $this->user('gestionnaire_budgetaire'));

        $service->annuler($dec);

        $this->convention->refresh();
        $this->assertEquals(0.0, (float) $this->convention->montant_decaisse);
        $this->assertEquals(20_000_000.0, (float) $this->convention->reliquat);
    }

    public function test_liaison_pap_via_http_et_gating(): void
    {
        // Lecteur peut voir la convention.
        $this->actingAs($this->user('lecteur'))->get(route('conventions.show', $this->convention))->assertOk();

        // Gestionnaire (convention_financement.modifier) enregistre un décaissement rattaché à une activité.
        $this->actingAs($this->user('gestionnaire_budgetaire'))->post(route('decaissements.store', $this->convention), [
            'reference' => 'DEC-HTTP', 'objet' => 'Financement activité',
            'montant' => 3_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'date_decaissement' => '2026-04-01', 'statut' => 'execute',
            'activite_id' => $this->activite->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('decaissements_convention', [
            'reference' => 'DEC-HTTP', 'activite_id' => $this->activite->id, 'convention_id' => $this->convention->id,
        ]);

        // Lecteur ne peut pas enregistrer de décaissement.
        $this->actingAs($this->user('lecteur'))->post(route('decaissements.store', $this->convention), [
            'reference' => 'DEC-NO', 'objet' => 'x', 'montant' => 1000, 'devise' => 'XAF',
            'taux_change' => 1, 'date_decaissement' => '2026-04-01', 'statut' => 'execute',
        ])->assertForbidden();
    }
}
