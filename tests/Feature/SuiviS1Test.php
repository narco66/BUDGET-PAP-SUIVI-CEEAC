<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use App\Services\Suivi\CollecteJustificatifService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviPermissionsSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase S1 — GED / justificatifs obligatoires par collecte. */
class SuiviS1Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private Activite $activite;
    private CampagneSuivi $campagne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, SuiviPermissionsSeeder::class, WorkflowPermissionsSeeder::class, WorkflowSeeder::class]);
        config(['suivi.justificatif_obligatoire' => true]);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-S1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-S1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-S1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP-S1', 'intitule' => 'SP']);
        $this->activite = Activite::create(['sous_produit_id' => $sp->id, 'produit_id' => $prod->id, 'code' => 'ACT-S1', 'intitule' => 'A', 'montant_total' => 1000]);
        $this->campagne = CampagneSuivi::create(['exercice_id' => $this->exercice->id, 'libelle' => 'S1', 'type' => 'trimestriel', 'periode' => '2026-S1', 'statut' => 'ouverte']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function collecteVerifiee(): Collecte
    {
        $this->actingAs($this->user('point_focal'))->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
            'taux_physique' => 55, 'montant_realise' => 550,
        ]);
        $c = Collecte::first();
        $this->actingAs($this->user('chef_service'))->post(route('suivi.collecte.verifier', $c));

        return $c->fresh();
    }

    public function test_validation_bloquee_sans_justificatif(): void
    {
        $c = $this->collecteVerifiee();

        $this->actingAs($this->user('controleur_financier'))
            ->post(route('suivi.collecte.valider', $c))
            ->assertRedirect()
            ->assertSessionHas('erreur');

        $this->assertSame('verifiee', $c->fresh()->statut);
    }

    public function test_validation_autorisee_avec_piece(): void
    {
        $c = $this->collecteVerifiee();
        $c->pieces()->create([
            'nom' => 'pv.pdf', 'chemin' => 'suivi/pv.pdf', 'type_mime' => 'application/pdf',
            'taille' => 100, 'categorie' => 'justificatif', 'televerse_par' => $c->saisi_par,
        ]);

        $this->assertTrue(app(CollecteJustificatifService::class)->possedeJustificatif($c->fresh()));

        $this->actingAs($this->user('controleur_financier'))
            ->post(route('suivi.collecte.valider', $c->fresh()))
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertSame('validee', $c->fresh()->statut);
    }

    public function test_vue_campagne_affiche_widget_justificatif(): void
    {
        $this->actingAs($this->user('point_focal'))->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
            'taux_physique' => 40, 'montant_realise' => 400,
        ]);

        $this->actingAs($this->user('commissaire'))
            ->get(route('suivi.show', $this->campagne))
            ->assertOk()
            ->assertSee('Justificatifs');
    }
}
