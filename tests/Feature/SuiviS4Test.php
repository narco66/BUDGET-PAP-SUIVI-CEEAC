<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\RealisationProduit;
use App\Models\SousProduit;
use App\Models\Tache;
use App\Models\User;
use App\Models\AlertePerformance;
use App\Services\Suivi\SuiviAuditService;
use App\Services\Suivi\SuiviPhysiqueService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviPermissionsSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase S4 — consolidation produits, quantités, audit, exports, permissions suivi_physique.* */
class SuiviS4Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private Activite $activite;

    private CampagneSuivi $campagne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, SuiviPermissionsSeeder::class, WorkflowPermissionsSeeder::class, WorkflowSeeder::class]);
        config(['suivi.justificatif_obligatoire' => false]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-S4', 'intitule' => 'Pilier S4']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-S4', 'intitule' => 'Axe S4']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-S4', 'intitule' => 'Produit S4']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP-S4', 'intitule' => 'SP S4']);
        $this->activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT-S4', 'intitule' => 'Activité S4', 'montant_total' => 1000,
        ]);

        $this->campagne = CampagneSuivi::create([
            'exercice_id' => $this->exercice->id,
            'libelle' => 'T1 S4',
            'type' => 'trimestriel',
            'periode' => '2026-T1-S4',
            'statut' => 'ouverte',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_saisie_par_quantites_calcule_taux_physique(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
                'quantite_prevue' => 100,
                'quantite_realisee' => 75,
                'montant_realise' => 750,
            ])
            ->assertRedirect();

        $c = Collecte::first();
        $this->assertEquals(75.0, (float) $c->taux_physique);
    }

    public function test_roll_up_taches_vers_activite(): void
    {
        Tache::create(['activite_id' => $this->activite->id, 'code' => 'T1', 'intitule' => 'T1', 'taux_realisation' => 40]);
        Tache::create(['activite_id' => $this->activite->id, 'code' => 'T2', 'intitule' => 'T2', 'taux_realisation' => 60]);

        $ok = app(SuiviPhysiqueService::class)->synchroniserTauxDepuisTaches($this->activite->fresh());

        $this->assertTrue($ok);
        $this->assertEquals(50.0, (float) $this->activite->fresh()->taux_realisation_physique);
    }

    public function test_cloture_consolide_produits_et_alertes(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
                'taux_physique' => 30,
                'montant_realise' => 300,
                'cause_ecart' => 'Retard fournisseur',
            ]);
        $c = Collecte::first();
        $this->actingAs($this->user('chef_service'))->post(route('suivi.collecte.verifier', $c));
        $this->actingAs($this->user('controleur_financier'))->post(route('suivi.collecte.valider', $c->fresh()));

        $this->actingAs($this->user('commissaire'))
            ->post(route('suivi.cloturer', $this->campagne))
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertSame('cloturee', $this->campagne->fresh()->statut);
        $this->assertTrue(RealisationProduit::where('campagne_id', $this->campagne->id)->exists());
        $this->assertTrue(AlertePerformance::where('campagne_id', $this->campagne->id)->where('resolu', false)->exists());

        $this->actingAs($this->user('commissaire'))
            ->get(route('suivi.show', $this->campagne))
            ->assertOk()
            ->assertSee('Alertes performance');
    }

    public function test_audit_persister_enregistre_snapshot(): void
    {
        app(SuiviAuditService::class)->executer($this->exercice->id, $this->user('commissaire'), true);

        $this->assertDatabaseHas('audit_suivi_physique', [
            'exercice_id' => $this->exercice->id,
        ]);
    }

    public function test_audit_suivi_avec_sections(): void
    {
        $rapport = app(SuiviAuditService::class)->executer($this->exercice->id);

        $this->assertArrayHasKey('score_conformite', $rapport);
        $this->assertArrayHasKey('sections', $rapport);
        $this->assertArrayHasKey('campagnes', $rapport['sections']);
        $this->assertArrayHasKey('justificatifs', $rapport['sections']);
    }

    public function test_commande_suivi_audit(): void
    {
        $this->artisan('suivi:audit', ['--exercice' => 2026])
            ->expectsOutputToContain('Score conformité')
            ->assertSuccessful();
    }

    public function test_permissions_suivi_physique_alias(): void
    {
        $user = $this->user('point_focal');
        $this->assertTrue($user->can('suivi_physique.create'));
        $this->assertFalse($user->can('suivi_physique.validate'));
    }

    public function test_export_collectes_csv(): void
    {
        Collecte::create([
            'campagne_id' => $this->campagne->id,
            'activite_id' => $this->activite->id,
            'taux_physique' => 50,
            'montant_realise' => 500,
            'statut' => 'saisie',
        ]);

        $this->actingAs($this->user('commissaire'))
            ->get(route('suivi.export.collectes', [$this->campagne, 'csv']))
            ->assertOk();
    }

    public function test_campagne_mensuelle(): void
    {
        $this->actingAs($this->user('chef_service'))
            ->post(route('suivi.store'), [
                'exercice_id' => $this->exercice->id,
                'libelle' => 'Suivi janvier',
                'type' => 'mensuel',
                'periode' => '2026-M01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campagnes_suivi', ['periode' => '2026-M01', 'type' => 'mensuel']);
    }

    public function test_parcours_e2e_saisie_validation_cloture(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
                'quantite_prevue' => 10,
                'quantite_realisee' => 8,
                'montant_realise' => 800,
            ])
            ->assertRedirect();

        $c = Collecte::first();
        $this->actingAs($this->user('chef_service'))->post(route('suivi.collecte.verifier', $c))->assertRedirect();
        $this->actingAs($this->user('controleur_financier'))->post(route('suivi.collecte.valider', $c->fresh()))->assertRedirect();

        $this->assertEquals(80.0, (float) $this->activite->fresh()->taux_realisation_physique);

        $this->actingAs($this->user('commissaire'))->post(route('suivi.cloturer', $this->campagne))->assertRedirect();

        $rapport = app(SuiviAuditService::class)->executer($this->exercice->id);
        $this->assertFalse(
            app(SuiviAuditService::class)->depasseSeuil($rapport['anomalies'], 'elevee'),
            json_encode(collect($rapport['anomalies'])->where('gravite', 'elevee')->values())
        );
    }

    public function test_documentation_utilisateur_existe(): void
    {
        $path = base_path('docs/module-suivi-physique.md');
        $this->assertFileExists($path);
        $contenu = file_get_contents($path);
        $this->assertStringContainsString('suivi:audit', $contenu);
        $this->assertStringContainsString('CampagneSuivi', $contenu);
    }

    public function test_seeder_suivi_physique(): void
    {
        $this->seed(RoleSeeder::class);
        User::factory()->create(['actif' => true])->assignRole('point_focal');
        $this->artisan('db:seed', ['--class' => 'SuiviPhysiqueSeeder'])->assertSuccessful();
    }
}
