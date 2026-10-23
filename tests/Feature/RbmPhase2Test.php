<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Mesure;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use Database\Seeders\RbmGranularPermissionsSeeder;
use Database\Seeders\RbmPermissionsSeeder;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** Tests phase 2 — import, mesures IOV, permissions granulaires. */
class RbmPhase2Test extends TestCase
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
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_page_import_rbm(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.import.form', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Imports Excel');
    }

    public function test_saisie_et_validation_mesure(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P', 'statut' => 'actif']);
        $ind = Indicateur::create([
            'rbm_type' => Pilier::class, 'rbm_id' => $pilier->id,
            'code' => 'IND-1', 'libelle' => 'Test', 'type' => 'strategique',
            'periodicite' => 'trimestriel', 'valeur_cible' => 100, 'seuil_alerte' => 50,
        ]);

        $this->actingAs($this->admin())
            ->post(route('rbm.mesures.store', $ind), [
                'periode' => '2026-T1',
                'valeur_realisee' => 80,
                'commentaire' => 'Test mesure',
            ])
            ->assertRedirect();

        $mesure = Mesure::first();
        $this->assertNotNull($mesure);
        $this->assertEquals('vert', $mesure->feu);
        $this->assertEquals('saisie', $mesure->statut);

        $this->actingAs($this->admin())
            ->post(route('rbm.mesures.valider', $mesure))
            ->assertRedirect();

        $this->assertEquals('validee', $mesure->fresh()->statut);
    }

    public function test_permission_granulaire_activite(): void
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo(['rbm.voir', 'activite.gerer']);

        $this->actingAs($user)
            ->get(route('rbm.create', 'activite').'?parent=1&exercice='.$this->exercice->id)
            ->assertOk();
    }

    public function test_permission_granulaire_refuse_sans_droit(): void
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo(['rbm.voir', 'pilier.voir']);

        $this->actingAs($user)
            ->get(route('rbm.create', 'pilier').'?parent='.$this->exercice->id.'&exercice='.$this->exercice->id)
            ->assertForbidden();
    }

    public function test_factory_pilier(): void
    {
        $pilier = Pilier::factory()->create(['exercice_id' => $this->exercice->id]);
        $this->assertDatabaseHas('piliers', ['id' => $pilier->id, 'code' => $pilier->code]);
    }
}
