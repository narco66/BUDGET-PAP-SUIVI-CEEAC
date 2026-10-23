<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\RealisationSousProduit;
use App\Models\SousProduit;
use App\Models\Tache;
use App\Models\User;
use App\Services\Suivi\SuiviPhysiqueService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviPermissionsSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase S3 — roll-up tâches, enrichissement collecte, consolidation sous-produits. */
class SuiviS3Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private Activite $activite;
    private CampagneSuivi $campagne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, SuiviPermissionsSeeder::class, WorkflowPermissionsSeeder::class, WorkflowSeeder::class]);
        config(['suivi.justificatif_obligatoire' => false, 'suivi.sync_taches_auto' => true]);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-S3', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-S3', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-S3', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP-S3', 'intitule' => 'SP']);
        $this->activite = Activite::create(['sous_produit_id' => $sp->id, 'produit_id' => $prod->id, 'code' => 'ACT-S3', 'intitule' => 'A', 'montant_total' => 2000]);
        $this->campagne = CampagneSuivi::create(['exercice_id' => $this->exercice->id, 'libelle' => 'S3', 'type' => 'trimestriel', 'periode' => '2026-S3', 'statut' => 'ouverte']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_saisie_enrichie_depuis_taches_sans_taux(): void
    {
        Tache::create(['activite_id' => $this->activite->id, 'code' => 'T1', 'intitule' => 'T1', 'taux_realisation' => 80]);
        Tache::create(['activite_id' => $this->activite->id, 'code' => 'T2', 'intitule' => 'T2', 'taux_realisation' => 60]);

        $this->actingAs($this->user('point_focal'))->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
            'montant_realise' => 1000,
        ])->assertRedirect();

        $this->assertEquals(70.0, (float) Collecte::first()->taux_physique);
    }

    public function test_validation_synchronise_taches_sur_activite(): void
    {
        Tache::create(['activite_id' => $this->activite->id, 'code' => 'T1', 'intitule' => 'T1', 'taux_realisation' => 90]);
        Tache::create(['activite_id' => $this->activite->id, 'code' => 'T2', 'intitule' => 'T2', 'taux_realisation' => 70]);

        $this->actingAs($this->user('point_focal'))->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
            'taux_physique' => 45, 'montant_realise' => 900,
        ]);
        $c = Collecte::first();
        $this->actingAs($this->user('chef_service'))->post(route('suivi.collecte.verifier', $c));
        $this->actingAs($this->user('controleur_financier'))->post(route('suivi.collecte.valider', $c->fresh()));

        $this->assertEquals(80.0, (float) $this->activite->fresh()->taux_realisation_physique);
    }

    public function test_cloture_consolide_sous_produits(): void
    {
        Collecte::create([
            'campagne_id' => $this->campagne->id, 'activite_id' => $this->activite->id,
            'taux_physique' => 65, 'montant_realise' => 1300, 'statut' => 'validee',
        ]);

        app(SuiviPhysiqueService::class)->cloturerCampagne($this->campagne);

        $this->assertTrue(RealisationSousProduit::where('campagne_id', $this->campagne->id)->exists());
        $rsp = RealisationSousProduit::first();
        $this->assertEquals(65.0, (float) $rsp->taux_physique);
        $this->assertSame(1, $rsp->nb_collectes_validees);
    }
}
