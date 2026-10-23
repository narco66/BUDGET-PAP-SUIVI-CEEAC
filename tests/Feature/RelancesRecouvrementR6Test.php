<?php

namespace Tests\Feature;

use App\Models\AppelFonds;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\Relance;
use App\Models\User;
use App\Services\Recettes\AppelFondsService;
use App\Services\Recettes\RelanceService;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** Phase R6 — relances de recouvrement : escalade, historique, génération automatique. */
class RelancesRecouvrementR6Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private EtatMembre $etat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->etat = EtatMembre::create(['code' => 'TCD', 'pays' => 'Tchad', 'cle_repartition' => 8, 'actif' => true]);
    }

    private function appel(array $over = []): AppelFonds
    {
        return app(AppelFondsService::class)->emettre(array_merge([
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-'.uniqid(),
            'objet' => 'Contribution 2026',
            'montant_appele' => 10_000_000,
            'date_emission' => '2026-01-10',
            'date_echeance' => '2026-02-01',
        ], $over), User::factory()->create()->assignRole('gestionnaire_budgetaire'));
    }

    public function test_table_existe(): void
    {
        $this->assertTrue(Schema::hasTable('relances_recouvrements'));
    }

    public function test_relance_escalade_le_niveau_et_snapshot_le_solde(): void
    {
        $service = app(RelanceService::class);
        $appel = $this->appel();

        $r1 = $service->relancer($appel, ['canal' => 'courrier']);
        $r2 = $service->relancer($appel->fresh(), ['canal' => 'email']);

        $this->assertSame(1, $r1->niveau);
        $this->assertSame(2, $r2->niveau);
        $this->assertEquals(10_000_000.0, (float) $r1->montant_du);
    }

    public function test_relance_impossible_si_solde(): void
    {
        $service = app(RelanceService::class);
        $appel = $this->appel();
        app(AppelFondsService::class)->enregistrerRecouvrement($appel, 10_000_000); // soldé

        $this->expectException(RuntimeException::class);
        $service->relancer($appel->fresh(), ['canal' => 'courrier']);
    }

    public function test_generation_automatique_pour_appels_en_retard(): void
    {
        $this->appel(['reference' => 'AF-RETARD']); // échéance 2026-02-01 passée (date courante 2026-06-12)
        $this->appel(['reference' => 'AF-OK', 'date_echeance' => now()->addMonth()->toDateString()]); // pas en retard

        $count = app(RelanceService::class)->genererAutomatiques($this->exercice->id);

        $this->assertSame(1, $count);
        $this->assertSame(1, Relance::where('automatique', true)->count());
    }

    public function test_generation_auto_respecte_le_delai(): void
    {
        $service = app(RelanceService::class);
        $this->appel(['reference' => 'AF-DELAI']);

        $this->assertSame(1, $service->genererAutomatiques($this->exercice->id));
        // Relance déjà émise aujourd'hui → pas de nouvelle dans le délai.
        $this->assertSame(0, $service->genererAutomatiques($this->exercice->id));
    }

    public function test_relance_via_http_gated(): void
    {
        $appel = $this->appel();

        $this->actingAs(User::factory()->create()->assignRole('lecteur'))
            ->post(route('appels-fonds.relancer', $appel), ['canal' => 'courrier'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create()->assignRole('gestionnaire_budgetaire'))
            ->post(route('appels-fonds.relancer', $appel), ['canal' => 'courrier'])
            ->assertRedirect();

        $this->assertSame(1, $appel->relances()->count());
    }

    public function test_commande_artisan_genere_les_relances(): void
    {
        $this->appel(['reference' => 'AF-CMD']);

        $this->artisan('recettes:relances-auto', ['--exercice' => $this->exercice->id])
            ->assertSuccessful();

        $this->assertSame(1, Relance::where('automatique', true)->count());
    }
}
