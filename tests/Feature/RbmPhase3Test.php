<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Pilier;
use App\Models\RbmHistorique;
use App\Models\User;
use App\Imports\IndicateursImport;
use Database\Seeders\Pap2026Seeder;
use Database\Seeders\RbmGranularPermissionsSeeder;
use Database\Seeders\RbmPermissionsSeeder;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** Tests phase 3 — import indicateurs, historique, PDF. */
class RbmPhase3Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmPermissionsSeeder::class);
        $this->seed(RbmGranularPermissionsSeeder::class);
        $this->seed(RbmReferentielSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->seed(Pap2026Seeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_historique_exercice(): void
    {
        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();
        RbmHistorique::create([
            'historisable_type' => Pilier::class,
            'historisable_id' => $pilier->id,
            'action' => 'update',
            'donnees_avant' => ['intitule' => 'Avant'],
            'donnees_apres' => ['intitule' => 'Après'],
            'user_id' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('rbm.historique.index', $this->exercice))
            ->assertOk()
            ->assertSee('update');
    }

    public function test_historique_apres_modification_noeud(): void
    {
        $this->actingAs($this->admin());
        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();

        $this->put(route('rbm.update', ['pilier', $pilier->id]), [
            'exercice' => $this->exercice->id,
            'parent' => $this->exercice->id,
            'code' => $pilier->code,
            'intitule' => 'Intitulé modifié test',
            'ordre' => $pilier->ordre,
        ])->assertRedirect();

        $this->assertDatabaseHas('rbm_historiques', [
            'historisable_id' => $pilier->id,
            'action' => 'update',
        ]);
    }

    public function test_import_indicateurs(): void
    {
        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();

        $this->actingAs($this->admin())
            ->post(route('rbm.import.indicateurs'), [
                'exercice_id_indicateurs' => $this->exercice->id,
                'fichier_indicateurs' => UploadedFile::fake()->create('indicateurs.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ]);

        // L'import réel nécessite un fichier Excel valide ; on teste le service directement
        $import = new IndicateursImport($this->exercice->id);
        $import->collection(collect([[
            'pilier_code' => $pilier->code,
            'niveau' => 'pilier',
            'noeud_code' => $pilier->code,
            'indicateur_code' => 'IND-TEST-PH3',
            'libelle' => 'Indicateur test phase 3',
            'type' => 'strategique',
            'periodicite' => 'annuel',
        ]]));

        $this->assertEquals(1, $import->importes);
        $this->assertDatabaseHas('indicateurs', ['code' => 'IND-TEST-PH3']);
    }

    public function test_pdf_catalogue_indicateurs(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.pdf.indicateurs', $this->exercice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_historique_noeud(): void
    {
        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();

        $this->actingAs($this->admin())
            ->get(route('rbm.historique.noeud', ['pilier', $pilier->id]))
            ->assertOk();
    }
}
