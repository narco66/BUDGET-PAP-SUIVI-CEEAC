<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\PrevisionRecette;
use App\Models\Recette;
use App\Models\User;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase R2-B — couche applicative (routes, gating, CRUD prévisions, révision). */
class RecettesUiR2Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_lecteur_voit_le_tableau_de_bord_mais_pas_la_creation(): void
    {
        $lecteur = $this->user('lecteur');

        $this->actingAs($lecteur)->get(route('recettes.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('previsions-recettes.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('previsions-recettes.create'))->assertForbidden();
    }

    public function test_gestionnaire_cree_une_prevision(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');

        $response = $this->actingAs($gestionnaire)->post(route('previsions-recettes.store'), [
            'exercice_id' => $this->exercice->id,
            'reference' => 'PREV-UI-1',
            'montant_prevu' => 12_000_000,
            'scenario' => 'initial',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('previsions_recettes', [
            'reference' => 'PREV-UI-1',
            'exercice_id' => $this->exercice->id,
            'cree_par' => $gestionnaire->id,
        ]);
    }

    public function test_reference_dupliquee_rejetee_sur_meme_exercice(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        PrevisionRecette::create(['exercice_id' => $this->exercice->id, 'reference' => 'PREV-DUP', 'montant_prevu' => 1_000_000, 'statut' => 'brouillon']);

        $this->actingAs($gestionnaire)->post(route('previsions-recettes.store'), [
            'exercice_id' => $this->exercice->id,
            'reference' => 'PREV-DUP',
            'montant_prevu' => 2_000_000,
        ])->assertSessionHasErrors('reference');
    }

    public function test_revision_cree_une_nouvelle_version(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $prevision = PrevisionRecette::create([
            'exercice_id' => $this->exercice->id, 'reference' => 'PREV-REV', 'montant_prevu' => 1_000_000,
            'statut' => 'valide', 'est_courante' => true, 'version' => 1,
        ]);

        $this->actingAs($gestionnaire)->post(route('previsions-recettes.reviser', $prevision), [
            'montant_revise' => 1_500_000,
            'observations' => 'Révision UI',
        ])->assertRedirect();

        $this->assertFalse($prevision->fresh()->est_courante);
        $this->assertDatabaseHas('previsions_recettes', ['reference' => 'PREV-REV-V2', 'version' => 2, 'est_courante' => true]);
    }

    public function test_suppression_recette_comptabilisee_bloquee_via_http(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $prevision = PrevisionRecette::create([
            'exercice_id' => $this->exercice->id, 'reference' => 'PREV-LOCK', 'montant_prevu' => 1_000_000, 'statut' => 'valide',
        ]);

        // Une prévision validée ne peut pas être supprimée (garde + policy).
        $this->actingAs($gestionnaire)->delete(route('previsions-recettes.destroy', $prevision))->assertForbidden();
        $this->assertDatabaseHas('previsions_recettes', ['id' => $prevision->id, 'deleted_at' => null]);
    }
}
