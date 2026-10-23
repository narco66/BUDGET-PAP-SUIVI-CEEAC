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
use App\Services\Rbm\RbmReferentielService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase E2 — unicité codes activité/tâche et sous_produit_id obligatoire. */
class RbmPhaseE2Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private SousProduit $sousProduit;
    private Activite $activite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $this->sousProduit = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $this->activite = Activite::create([
            'sous_produit_id' => $this->sousProduit->id,
            'produit_id' => $prod->id,
            'code' => 'ACT1',
            'intitule' => 'Act',
            'montant_total' => 1000,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_unicite_code_activite_par_sous_produit(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Activite::create([
            'sous_produit_id' => $this->sousProduit->id,
            'produit_id' => $this->sousProduit->produit_id,
            'code' => 'ACT1',
            'intitule' => 'Doublon',
            'montant_total' => 500,
        ]);
    }

    public function test_unicite_code_tache_par_activite(): void
    {
        Tache::create([
            'activite_id' => $this->activite->id,
            'code' => 'T1',
            'intitule' => 'Tâche 1',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Tache::create([
            'activite_id' => $this->activite->id,
            'code' => 'T1',
            'intitule' => 'Doublon',
        ]);
    }

    public function test_activite_requiert_sous_produit(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sous-produit');

        Activite::create([
            'produit_id' => $this->sousProduit->produit_id,
            'code' => 'ACT-SANS-SP',
            'intitule' => 'Orpheline',
            'montant_total' => 100,
        ]);
    }

    public function test_refuse_doublon_code_activite_via_http(): void
    {
        $this->actingAs($this->user('administrateur'))
            ->post(route('rbm.store', 'activite'), [
                'parent' => $this->sousProduit->id,
                'exercice' => $this->exercice->id,
                'code' => 'ACT1',
                'intitule' => 'Autre activité',
                'montant_ceeac' => 100,
                'montant_ptf' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('code');
    }

    public function test_code_doublon_service_detecte_conflit(): void
    {
        $svc = app(RbmReferentielService::class);

        $this->assertTrue($svc->codeDoublon('activite', $this->sousProduit->id, 'ACT1'));
        $this->assertFalse($svc->codeDoublon('activite', $this->sousProduit->id, 'ACT-NEW'));
    }

    public function test_migration_contrainte_sous_produit_not_null(): void
    {
        $this->assertNotNull($this->activite->sous_produit_id);
        $this->assertDatabaseHas('activites', [
            'id' => $this->activite->id,
            'sous_produit_id' => $this->sousProduit->id,
        ]);
    }
}
