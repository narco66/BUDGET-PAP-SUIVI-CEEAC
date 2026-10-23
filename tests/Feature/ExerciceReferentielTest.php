<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use Database\Seeders\ExercicePermissionsSeeder;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciceReferentielTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ExercicePermissionsSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->admin = User::create([
            'name' => 'Admin Exercice', 'email' => 'admin.exercice@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_index_liste_exercices(): void
    {
        $this->actingAs($this->admin)->get(route('exercices.index'))
            ->assertOk()
            ->assertSee('Exercices budgétaires')
            ->assertSee('2026');
    }

    public function test_crud_exercice_brouillon(): void
    {
        $this->actingAs($this->admin)->post(route('exercices.store'), [
            'code' => '2027',
            'annee' => 2027,
            'libelle' => 'Exercice budgétaire 2027',
            'date_debut' => '2027-01-01',
            'date_fin' => '2027-12-31',
            'devise' => 'XAF',
        ])->assertRedirect();

        $exercice = Exercice::firstWhere('code', '2027');
        $this->assertNotNull($exercice);
        $this->assertSame('brouillon', $exercice->statut);

        $this->actingAs($this->admin)->put(route('exercices.update', $exercice), [
            'code' => '2027',
            'annee' => 2027,
            'libelle' => 'Exercice 2027 modifié',
            'date_debut' => '2027-01-01',
            'date_fin' => '2027-12-31',
            'devise' => 'XAF',
        ])->assertRedirect();

        $this->assertSame('Exercice 2027 modifié', $exercice->fresh()->libelle);

        $this->actingAs($this->admin)->delete(route('exercices.destroy', $exercice))
            ->assertRedirect(route('exercices.index'));
        $this->assertDatabaseMissing('exercices', ['id' => $exercice->id]);
    }

    public function test_workflow_ouverture_cloture(): void
    {
        $exercice = Exercice::create([
            'code' => '2028', 'annee' => 2028, 'libelle' => 'Test 2028',
            'statut' => 'brouillon', 'est_actif' => false,
            'date_debut' => '2028-01-01', 'date_fin' => '2028-12-31', 'devise' => 'XAF',
        ]);

        $this->actingAs($this->admin)->post(route('exercices.ouvrir', $exercice))
            ->assertRedirect();
        $this->assertSame('ouvert', $exercice->fresh()->statut);

        $this->actingAs($this->admin)->post(route('exercices.cloturer', $exercice))
            ->assertRedirect();
        $exercice->refresh();
        $this->assertSame('clos', $exercice->statut);
        $this->assertNotNull($exercice->date_cloture);
        $this->assertSame($this->admin->id, $exercice->cloture_par);
    }

    public function test_definir_exercice_actif(): void
    {
        $ex2026 = Exercice::firstWhere('annee', 2026);
        $ex2027 = Exercice::create([
            'code' => '2027B', 'annee' => 2027, 'libelle' => 'Exercice 2027',
            'statut' => 'ouvert', 'est_actif' => false,
            'date_debut' => '2027-01-01', 'date_fin' => '2027-12-31', 'devise' => 'XAF',
        ]);

        $this->actingAs($this->admin)->post(route('exercices.activer', $ex2027))
            ->assertRedirect();

        $this->assertFalse($ex2026->fresh()->est_actif);
        $this->assertTrue($ex2027->fresh()->est_actif);
    }

    public function test_exercice_clos_bloque_engagement(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        $exercice->update(['statut' => 'clos', 'date_cloture' => now(), 'cloture_par' => $this->admin->id]);

        $this->expectException(\RuntimeException::class);
        app(\App\Services\Budget\ExerciceService::class)->assertExerciceOuvert($exercice->id);
    }

    public function test_exercice_courant_prefere_actif(): void
    {
        $actif = Exercice::firstWhere('est_actif', true);
        $this->assertNotNull($actif);
        $this->assertSame($actif->id, \App\Services\Budget\ExerciceService::courant()?->id);
    }
}
