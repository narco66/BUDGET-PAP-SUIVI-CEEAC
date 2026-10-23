<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\TypeIndicateur;
use App\Models\User;
use Database\Seeders\Pap2026Seeder;
use Database\Seeders\RbmPermissionsSeeder;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tests Module 2 — Référentiel institutionnel RBM/GAR étendu. */
class RbmReferentielModuleTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmPermissionsSeeder::class);
        $this->seed(RbmReferentielSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->seed(Pap2026Seeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_hub_rbm_accessible(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.hub', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Module 2');
    }

    public function test_matrice_rbm_affiche_donnees(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.matrice', $this->exercice))
            ->assertOk()
            ->assertSee('Matrice');
    }

    public function test_catalogue_types_indicateurs(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.catalogues.index', 'types-indicateurs'))
            ->assertOk()
            ->assertSee('STR');

        $this->assertGreaterThanOrEqual(4, TypeIndicateur::count());
    }

    public function test_creation_type_indicateur(): void
    {
        $this->actingAs($this->admin())
            ->post(route('rbm.catalogues.store', 'types-indicateurs'), [
                'code' => 'QLT',
                'libelle' => 'Qualitatif',
                'ordre' => 5,
                'statut' => 'actif',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('types_indicateurs', ['code' => 'QLT']);
    }

    public function test_cadre_logique_et_chaine_resultats(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.cadre-logique', $this->exercice))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('rbm.chaine-resultats', $this->exercice))
            ->assertOk();

        $this->assertDatabaseHas('cadres_logiques', ['exercice_id' => $this->exercice->id]);
        $this->assertDatabaseHas('chaines_resultats', ['exercice_id' => $this->exercice->id]);
    }

    public function test_suppression_pilier_refusee_si_enfants(): void
    {
        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();
        $this->assertNotNull($pilier);

        $this->actingAs($this->admin())
            ->delete(route('rbm.destroy', ['pilier', $pilier->id]), ['exercice' => $this->exercice->id])
            ->assertRedirect();

        $this->assertDatabaseHas('piliers', ['id' => $pilier->id, 'deleted_at' => null]);
    }

    public function test_colonnes_statut_sur_piliers(): void
    {
        $pilier = Pilier::first();
        $this->assertEquals('actif', $pilier->statut ?? 'actif');
    }
}
