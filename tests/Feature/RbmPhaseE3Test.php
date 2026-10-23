<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Models\User;
use App\Imports\IndicateursImport;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase E3 — type/unité catalogue obligatoires sur les indicateurs IOV. */
class RbmPhaseE3Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private TypeIndicateur $typeOpe;
    private UniteMesure $unitePct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmReferentielSeeder::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $this->typeOpe = TypeIndicateur::where('code', 'OPE')->first();
        $this->unitePct = UniteMesure::where('code', 'PCT')->first();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_indicateur_requiert_type_et_unite(): void
    {
        $this->actingAs($this->user('administrateur'));
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $act = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT1', 'intitule' => 'Act', 'montant_total' => 1000,
        ]);

        $this->post(route('rbm.indicateurs.store', ['activite', $act->id]).'?exercice='.$this->exercice->id, [
            'code' => 'IND-E3',
            'libelle' => 'Sans catalogues',
            'periodicite' => 'trimestriel',
        ])->assertSessionHasErrors(['type_indicateur_id', 'unite_mesure_id']);
    }

    public function test_creation_indicateur_avec_catalogues(): void
    {
        $this->actingAs($this->user('administrateur'));
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $act = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT1', 'intitule' => 'Act', 'montant_total' => 1000,
        ]);

        $this->post(route('rbm.indicateurs.store', ['activite', $act->id]).'?exercice='.$this->exercice->id, [
            'code' => 'IND-OK',
            'libelle' => 'Avec catalogues',
            'type_indicateur_id' => $this->typeOpe->id,
            'unite_mesure_id' => $this->unitePct->id,
            'periodicite' => 'trimestriel',
            'valeur_cible' => 100,
        ])->assertRedirect();

        $ind = Indicateur::where('code', 'IND-OK')->first();
        $this->assertSame($this->typeOpe->id, $ind->type_indicateur_id);
        $this->assertSame($this->unitePct->id, $ind->unite_mesure_id);
        $this->assertSame('operationnel', $ind->type);
        $this->assertSame('%', $ind->unite);
    }

    public function test_import_indicateur_rattache_catalogues(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);

        $import = new IndicateursImport($this->exercice->id);
        $import->collection(collect([[

            'pilier_code' => 'P1',
            'niveau' => 'pilier',
            'noeud_code' => 'P1',
            'indicateur_code' => 'IND-IMP-E3',
            'libelle' => 'Import E3',
            'type' => 'strategique',
            'unite' => '%',
            'periodicite' => 'annuel',
        ]]));

        $ind = Indicateur::where('code', 'IND-IMP-E3')->first();
        $this->assertNotNull($ind);
        $this->assertSame(TypeIndicateur::where('code', 'STR')->value('id'), $ind->type_indicateur_id);
        $this->assertSame($this->unitePct->id, $ind->unite_mesure_id);
        $this->assertSame('strategique', $ind->type);
    }

    public function test_synchronisation_legacy_depuis_type_seul(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);

        $ind = Indicateur::create([
            'rbm_type' => Pilier::class,
            'rbm_id' => $pilier->id,
            'code' => 'IND-SYNC',
            'libelle' => 'Sync auto',
            'type' => 'financier',
            'unite' => 'XAF',
            'periodicite' => 'trimestriel',
        ]);

        $this->assertSame(TypeIndicateur::where('code', 'FIN')->value('id'), $ind->type_indicateur_id);
        $this->assertSame(UniteMesure::where('code', 'XAF')->value('id'), $ind->unite_mesure_id);
        $this->assertSame('financier', $ind->fresh()->type);
    }
}
