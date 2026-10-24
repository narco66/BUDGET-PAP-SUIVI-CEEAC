<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbmSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->chaine();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function chaine(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'Intégration', 'ordre' => 1]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Gouvernance']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit 1']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'Sous-produit 1']);
        $act = Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $produit->id,
            'code' => 'ACT1',
            'intitule' => 'Activité 1',
            'montant_ceeac' => 100000,
            'montant_ptf' => 50000,
            'montant_total' => 150000,
        ]);
        Tache::create(['activite_id' => $act->id, 'code' => 'T1', 'intitule' => 'Tâche 1', 'statut' => 'en_cours']);
    }

    public function test_lecteur_sans_rbm_est_refuse(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->getJson(route('api.rbm.hub', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }

    public function test_arbre_matrice_et_montant_decimal(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.rbm.hub', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('compteurs.piliers', 1)
            ->assertJsonPath('compteurs.activites', 1)
            ->assertJsonPath('compteurs.montant_total', '150000.00');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.rbm.vue', ['exercice' => $this->exercice->id, 'jeu' => 'inconnu']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'jeu_invalide');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.rbm.vue', ['exercice' => $this->exercice->id, 'jeu' => 'arbre']))
            ->assertOk()
            ->assertJsonPath('lignes.0.code', 'P1')
            ->assertJsonPath('lignes.0.montant', '150000.00');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.rbm.vue', ['exercice' => $this->exercice->id, 'jeu' => 'matrice']))
            ->assertOk()
            ->assertJsonPath('lignes.0.activite', 'ACT1')
            ->assertJsonPath('lignes.0.montant', '150000.00');
    }

    public function test_creation_et_edition_noeud_sans_ieee(): void
    {
        $admin = $this->user('administrateur');
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $ex = $this->exercice->id;

        $this->actingAs($gestionnaire)
            ->postJson(route('api.rbm.niveaux.store', 'pilier'), [
                'parent' => $ex, 'exercice' => $ex, 'code' => 'PX', 'intitule' => 'Refus',
            ])
            ->assertForbidden();

        $creation = $this->actingAs($admin)
            ->postJson(route('api.rbm.niveaux.store', 'pilier'), [
                'parent' => $ex, 'exercice' => $ex, 'code' => 'P2', 'intitule' => 'Paix', 'ordre' => 2,
            ]);
        $creation->assertCreated()
            ->assertJsonPath('code', 'cree')
            ->assertJsonPath('data.code', 'P2');
        $pilierId = $creation->json('data.id');

        $this->actingAs($admin)
            ->putJson(route('api.rbm.niveaux.update', ['pilier', $pilierId]), [
                'parent' => $ex, 'exercice' => $ex, 'code' => 'P2', 'intitule' => 'Paix et sécurité',
            ])
            ->assertOk()
            ->assertJsonPath('data.intitule', 'Paix et sécurité');

        $sp = SousProduit::query()->first();
        $this->actingAs($admin)
            ->postJson(route('api.rbm.niveaux.store', 'activite'), [
                'parent' => $sp->id,
                'exercice' => $ex,
                'code' => 'ACT2',
                'intitule' => 'Activité SPA',
                'montant_ceeac' => '200000.50',
                'montant_ptf' => '100000.25',
            ])
            ->assertCreated()
            ->assertJsonPath('data.montant_total', '300000.75')
            ->assertJsonPath('data.champs.montant_ceeac', '200000.50');
    }
}
