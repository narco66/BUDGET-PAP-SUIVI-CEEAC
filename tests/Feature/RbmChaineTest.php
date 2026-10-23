<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Models\User;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbmChaineTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmReferentielSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_construction_complete_de_la_chaine_rbm(): void
    {
        $this->actingAs($this->user('administrateur'));
        $ex = $this->exercice->id;

        $this->post(route('rbm.store', 'pilier'), ['parent' => $ex, 'exercice' => $ex, 'code' => 'P1', 'intitule' => 'Intégration', 'ordre' => 1])->assertRedirect();
        $pilier = Pilier::firstWhere('code', 'P1');
        $this->assertEquals($ex, $pilier->exercice_id);

        $this->post(route('rbm.store', 'axe'), ['parent' => $pilier->id, 'exercice' => $ex, 'code' => 'A1', 'intitule' => 'Gouvernance'])->assertRedirect();
        $axe = Axe::firstWhere('code', 'A1');
        $this->assertEquals($pilier->id, $axe->pilier_id);

        $this->post(route('rbm.store', 'produit'), ['parent' => $axe->id, 'exercice' => $ex, 'code' => 'PR1', 'intitule' => 'Produit 1'])->assertRedirect();
        $produit = Produit::firstWhere('code', 'PR1');

        $this->post(route('rbm.store', 'sous_produit'), ['parent' => $produit->id, 'exercice' => $ex, 'code' => 'SP1', 'intitule' => 'Sous-produit 1'])->assertRedirect();
        $sp = SousProduit::firstWhere('code', 'SP1');

        $this->post(route('rbm.store', 'activite'), [
            'parent' => $sp->id, 'exercice' => $ex,
            'code' => 'ACT1', 'intitule' => 'Activité 1',
            'montant_ceeac' => 100000, 'montant_ptf' => 50000,
            'trimestres' => [1, 3],
        ])->assertRedirect();

        $act = Activite::firstWhere('code', 'ACT1');
        $this->assertEquals($sp->id, $act->sous_produit_id);
        $this->assertEquals($produit->id, $act->produit_id);          // dénormalisation
        $this->assertEquals(150000, (float) $act->montant_total);     // somme contrôlée
        $this->assertTrue((bool) $act->trim_1);
        $this->assertFalse((bool) $act->trim_2);
        $this->assertTrue((bool) $act->trim_3);

        $this->post(route('rbm.store', 'tache'), ['parent' => $act->id, 'exercice' => $ex, 'code' => 'T1', 'intitule' => 'Tâche 1', 'statut' => 'en_cours'])->assertRedirect();
        $this->assertDatabaseHas('taches', ['code' => 'T1', 'activite_id' => $act->id, 'statut' => 'en_cours']);
    }

    public function test_suppression_refusee_si_enfants(): void
    {
        $this->actingAs($this->user('administrateur'));
        $ex = $this->exercice->id;
        $pilier = Pilier::create(['exercice_id' => $ex, 'code' => 'P1', 'intitule' => 'P']);
        Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);

        $this->delete(route('rbm.destroy', ['pilier', $pilier->id]), ['exercice' => $ex])
            ->assertRedirect();
        $this->assertDatabaseHas('piliers', ['id' => $pilier->id, 'deleted_at' => null]);
    }

    public function test_niveau_inconnu_renvoie_404(): void
    {
        $this->actingAs($this->user('administrateur'));
        $this->get(route('rbm.create', 'inconnu').'?exercice='.$this->exercice->id)->assertNotFound();
    }

    public function test_role_sans_creation_est_refuse(): void
    {
        // gestionnaire_budgetaire : a rbm.voir (*.voir) mais pas rbm.creer
        $this->actingAs($this->user('gestionnaire_budgetaire'));
        $this->get(route('rbm.create', 'pilier').'?exercice='.$this->exercice->id)->assertForbidden();
        $this->get(route('rbm.arbre', $this->exercice))->assertOk();
    }

    public function test_role_sans_lecture_rbm_est_refuse(): void
    {
        // point_focal : pas de rbm.voir
        $this->actingAs($this->user('point_focal'));
        $this->get(route('rbm.arbre', $this->exercice))->assertForbidden();
    }

    public function test_formulaires_se_rendent_create_et_edit(): void
    {
        $this->actingAs($this->user('administrateur'));
        $ex = $this->exercice->id;

        // formulaire de création d'un pilier
        $this->get(route('rbm.create', 'pilier')."?parent={$ex}&exercice={$ex}")->assertOk();

        // formulaire d'édition d'une activité (champs riches : selects, décimaux, trimestres)
        $pilier = Pilier::create(['exercice_id' => $ex, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $act = Activite::create(['sous_produit_id' => $sp->id, 'produit_id' => $produit->id, 'code' => 'ACT1', 'intitule' => 'ACT', 'trim_1' => true, 'trim_4' => true]);

        $this->get(route('rbm.edit', ['activite', $act->id])."?exercice={$ex}")
            ->assertOk()
            ->assertSee('ACT1');

        // page indicateurs
        $this->get(route('rbm.indicateurs.index', ['activite', $act->id])."?exercice={$ex}")->assertOk();
    }

    public function test_ajout_indicateur_sur_activite(): void
    {
        $this->actingAs($this->user('administrateur'));
        $ex = $this->exercice->id;
        $pilier = Pilier::create(['exercice_id' => $ex, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $act = Activite::create(['sous_produit_id' => $sp->id, 'produit_id' => $produit->id, 'code' => 'ACT1', 'intitule' => 'ACT']);

        $this->post(route('rbm.indicateurs.store', ['activite', $act->id]).'?exercice='.$ex, [
            'code' => 'IND1', 'libelle' => 'Taux de réalisation',
            'type_indicateur_id' => TypeIndicateur::where('code', 'OPE')->value('id'),
            'unite_mesure_id' => UniteMesure::where('code', 'PCT')->value('id'),
            'periodicite' => 'trimestriel', 'valeur_cible' => 100,
        ])->assertRedirect();

        $this->assertDatabaseHas('indicateurs', [
            'code' => 'IND1', 'rbm_type' => Activite::class, 'rbm_id' => $act->id,
        ]);
    }
}
