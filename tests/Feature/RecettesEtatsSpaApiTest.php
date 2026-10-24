<?php

namespace Tests\Feature;

use App\Models\AppelFonds;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Recettes\AppelFondsService;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecettesEtatsSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private EtatMembre $etat;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->etat = EtatMembre::create(['code' => 'GAB', 'pays' => 'Gabon', 'cle_repartition' => 12, 'actif' => true]);

        $appel = app(AppelFondsService::class)->emettre([
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-GAB-ETAT',
            'objet' => 'Contribution 2026',
            'montant_appele' => 10_000_000,
            'date_emission' => '2026-01-10',
            'date_echeance' => '2026-02-01',
            'taux_penalite' => 5,
        ], User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('gestionnaire_budgetaire'));
        app(AppelFondsService::class)->enregistrerRecouvrement($appel, 3_000_000);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_lecteur_voit_sans_exporter(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.recettes.etats', ['exercice' => $this->exercice->id, 'jeu' => 'mobilisation']))
            ->assertOk()
            ->assertJsonPath('peut_exporter', false)
            ->assertJsonPath('exports', [])
            ->assertJsonPath('synthese_mobilisation.appele', '10000000.00')
            ->assertJsonPath('synthese_mobilisation.arriere', '7000000.00')
            ->assertJsonPath('synthese_mobilisation.taux', '30.0');

        $gabon = collect($this->actingAs($this->user('lecteur'))
            ->getJson(route('api.recettes.etats', ['exercice' => $this->exercice->id, 'jeu' => 'mobilisation']))
            ->json('lignes'))->firstWhere('code', 'GAB');

        $this->assertSame('10000000.00', $gabon['appele']);
        $this->assertSame('3000000.00', $gabon['recouvre']);
        $this->assertSame('7000000.00', $gabon['arriere']);
    }

    public function test_arrieres_et_financier_et_gating(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->getJson(route('api.recettes.etats'))
            ->assertForbidden();

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.recettes.etats', ['jeu' => 'inconnu']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'jeu_invalide');

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.recettes.etats', ['exercice' => $this->exercice->id, 'jeu' => 'arrieres']))
            ->assertOk()
            ->assertJsonPath('lignes.0.reference', 'AF-GAB-ETAT')
            ->assertJsonPath('lignes.0.solde', '7000000.00');

        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->getJson(route('api.recettes.etats', ['exercice' => $this->exercice->id, 'jeu' => 'financier']))
            ->assertOk()
            ->assertJsonPath('peut_exporter', true)
            ->assertJsonPath('synthese_mobilisation.recouvre', '3000000.00');

        $this->assertNotEmpty(
            $this->actingAs($this->user('gestionnaire_budgetaire'))
                ->getJson(route('api.recettes.etats', ['exercice' => $this->exercice->id, 'jeu' => 'financier']))
                ->json('exports.financier')
        );

        $this->assertInstanceOf(AppelFonds::class, AppelFonds::firstWhere('reference', 'AF-GAB-ETAT'));
    }
}
