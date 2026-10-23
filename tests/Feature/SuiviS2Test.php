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
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase S2 — policies, permissions suivi_physique.*, exports sécurisés. */
class SuiviS2Test extends TestCase
{
    use RefreshDatabase;

    private CampagneSuivi $campagne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SuiviPermissionsSeeder::class);
        config(['suivi.justificatif_obligatoire' => false]);

        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P-S2', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-S2', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-S2', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP-S2', 'intitule' => 'SP']);
        $activite = Activite::create(['sous_produit_id' => $sp->id, 'produit_id' => $prod->id, 'code' => 'ACT-S2', 'intitule' => 'A', 'montant_total' => 500]);

        $this->campagne = CampagneSuivi::create(['exercice_id' => $exercice->id, 'libelle' => 'S2', 'type' => 'trimestriel', 'periode' => '2026-S2', 'statut' => 'ouverte']);
        Collecte::create(['campagne_id' => $this->campagne->id, 'activite_id' => $activite->id, 'taux_physique' => 30, 'montant_realise' => 150, 'statut' => 'saisie']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_point_focal_peut_exporter_via_alias(): void
    {
        $this->assertFalse($this->user('point_focal')->can('suivi.exporter'));
        $this->assertFalse($this->user('point_focal')->can('suivi_physique.export'));
    }

    public function test_commissaire_peut_exporter_collectes(): void
    {
        $this->actingAs($this->user('commissaire'))
            ->get(route('suivi.export.collectes', [$this->campagne, 'csv']))
            ->assertOk();
    }

    public function test_point_focal_interdit_export(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->get(route('suivi.export.collectes', [$this->campagne, 'csv']))
            ->assertForbidden();
    }

    public function test_collecte_policy_bloque_modification_validee(): void
    {
        $c = Collecte::first();
        $c->update(['statut' => 'validee']);

        $this->assertFalse($this->user('point_focal')->can('update', $c));
        $this->assertTrue($this->user('commissaire')->can('validate', $c));
    }
}
