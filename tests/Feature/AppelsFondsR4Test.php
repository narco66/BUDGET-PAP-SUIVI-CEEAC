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
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** Phase R4 — appels de fonds : émission, recouvrement, arriérés, pénalités, anti-suppression. */
class AppelsFondsR4Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private EtatMembre $etat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->etat = EtatMembre::create(['code' => 'CMR', 'pays' => 'Cameroun', 'cle_repartition' => 10, 'actif' => true]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function appel(array $over = []): AppelFonds
    {
        return app(AppelFondsService::class)->emettre(array_merge([
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-'.uniqid(),
            'objet' => 'Contribution statutaire 2026',
            'montant_appele' => 10_000_000,
            'date_emission' => '2026-01-15',
            'date_echeance' => '2026-03-31',
            'taux_penalite' => 5,
        ], $over), $this->user('gestionnaire_budgetaire'));
    }

    public function test_table_existe(): void
    {
        $this->assertTrue(Schema::hasTable('appels_fonds'));
    }

    public function test_emission_initialise_statut_emis(): void
    {
        $appel = $this->appel();
        $this->assertSame('emis', $appel->statut);
        $this->assertEquals(10_000_000.0, (float) $appel->solde);
    }

    public function test_recouvrement_partiel_puis_total(): void
    {
        $service = app(AppelFondsService::class);
        $appel = $this->appel();

        $service->enregistrerRecouvrement($appel, 4_000_000);
        $this->assertSame('partiellement_recouvre', $appel->fresh()->statut);
        $this->assertEquals(6_000_000.0, (float) $appel->fresh()->solde);

        $service->enregistrerRecouvrement($appel->fresh(), 6_000_000);
        $this->assertSame('solde', $appel->fresh()->statut);
        $this->assertEquals(0.0, (float) $appel->fresh()->solde);
    }

    public function test_recouvrement_ne_depasse_pas_le_montant_appele(): void
    {
        $service = app(AppelFondsService::class);
        $appel = $this->appel();

        $this->expectException(RuntimeException::class);
        $service->enregistrerRecouvrement($appel, 12_000_000);
    }

    public function test_calcul_arriere_et_penalite_si_en_retard(): void
    {
        // Échéance passée, taux 5 % : pénalité = 5 % de l'arriéré.
        $appel = $this->appel(['date_echeance' => '2026-02-01']);
        app(AppelFondsService::class)->enregistrerRecouvrement($appel, 2_000_000);
        $appel = $appel->fresh();

        $this->assertTrue($appel->enRetard());
        $this->assertEquals(8_000_000.0, (float) $appel->solde);
        $this->assertEquals(400_000.0, (float) $appel->penalite); // 5 % de 8 000 000
    }

    public function test_appel_recouvre_ne_peut_pas_etre_supprime(): void
    {
        $service = app(AppelFondsService::class);
        $appel = $this->appel();
        $service->enregistrerRecouvrement($appel, 1_000_000);

        $this->expectException(RuntimeException::class);
        $appel->fresh()->delete();
    }

    public function test_gating_recouvrement_par_permission(): void
    {
        $appel = $this->appel();

        // Lecteur : ne peut pas créer d'appel.
        $this->actingAs($this->user('lecteur'))->get(route('appels-fonds.create'))->assertForbidden();

        // Gestionnaire budgétaire : recette.recouvrement → accès.
        $this->actingAs($this->user('gestionnaire_budgetaire'))->get(route('appels-fonds.create'))->assertOk();

        // Tableau de bord visible par un lecteur (recette.voir).
        $this->actingAs($this->user('lecteur'))->get(route('appels-fonds.index'))->assertOk();
    }
}
