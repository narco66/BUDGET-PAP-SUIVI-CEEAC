<?php

namespace Tests\Feature;

use App\Imports\PapImport;
use App\Models\Activite;
use App\Models\Axe;
use App\Models\Contribution;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\RecetteSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReprisePapRecettesTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
    }

    private function csv(string $contenu): string
    {
        $path = storage_path('app/test_pap_'.uniqid().'.csv');
        file_put_contents($path, $contenu);

        return $path;
    }

    public function test_import_pap_cree_la_chaine_complete(): void
    {
        $path = $this->csv(
            "pilier_code,pilier_intitule,axe_code,axe_intitule,produit_code,produit_intitule,sous_produit_code,sous_produit_intitule,activite_code,activite_intitule,montant_ceeac,montant_ptf\n".
            "201,Integration politique,2011,Gouvernance,20111,Comite des Sages,201110,CS,2011100,Fonctionnement,150000000,0\n".
            "201,Integration politique,2011,Gouvernance,20112,Mediation,201121,Cadre,2011210,Cadre regional,135000000,50000000\n"
        );

        $import = new PapImport($this->exercice->id);
        Excel::import($import, $path);
        @unlink($path);

        $this->assertSame(1, $import->piliers);
        $this->assertSame(1, $import->axes);
        $this->assertSame(2, $import->produits);
        $this->assertSame(2, $import->activites);

        $pilier = Pilier::firstWhere('code', '201');
        $this->assertNotNull($pilier);
        $this->assertEquals($this->exercice->id, $pilier->exercice_id);

        $act = Activite::firstWhere('code', '2011210');
        $this->assertEquals(185000000, (float) $act->montant_total); // 135M + 50M
        $this->assertEquals(50000000, (float) $act->montant_ptf);
    }

    public function test_import_pap_est_idempotent(): void
    {
        $ligne = "pilier_code,pilier_intitule,axe_code,axe_intitule,produit_code,produit_intitule,sous_produit_code,sous_produit_intitule,activite_code,activite_intitule,montant_ceeac,montant_ptf\n".
            "201,P,2011,A,20111,PR,201110,SP,2011100,ACT,100000000,0\n";

        Excel::import(new PapImport($this->exercice->id), $p1 = $this->csv($ligne));
        Excel::import(new PapImport($this->exercice->id), $p2 = $this->csv($ligne));
        @unlink($p1);
        @unlink($p2);

        $this->assertSame(1, Pilier::where('code', '201')->count());
        $this->assertSame(1, Activite::where('code', '2011100')->count());
    }

    public function test_import_pap_protege_par_permission(): void
    {
        $this->seed(RoleSeeder::class);
        // point_focal n'a pas rbm.creer
        $user = User::factory()->create(['actif' => true])->assignRole('point_focal');

        $this->actingAs($user)
            ->post(route('budget.import.pap'), ['exercice_id' => $this->exercice->id])
            ->assertForbidden();
    }

    public function test_reprise_recettes_externes(): void
    {
        $this->seed(RecetteSeeder::class);

        $externe = Contribution::where('exercice_id', $this->exercice->id)->whereNull('etat_membre_id');
        $this->assertSame(11, $externe->count());
        $this->assertEquals(14_030_081_000, (float) $externe->sum('montant_appele'));
    }
}
