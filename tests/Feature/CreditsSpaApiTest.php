<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\User;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditsSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, NomenclatureBudgetaireSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2033, 'libelle' => 'Ex 2033', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_credits_liste_creation_et_montant_chaine(): void
    {
        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => BudgetRubrique::first()->id,
            'code' => 'CRD-SPA-1',
            'intitule' => 'Ligne crédits SPA',
            'montant_ouvert' => 125000.25,
            'montant_engage' => 0,
        ]);

        $lecteur = $this->user('lecteur');
        $reponse = $this->actingAs($lecteur)
            ->getJson(route('api.budget.credits.index', ['exercice' => $this->exercice->id]));
        $reponse->assertOk()
            ->assertJsonPath('totaux.ouvert', '125000.25')
            ->assertJsonPath('totaux.disponible', '125000.25')
            ->assertJsonPath('lignes.0.montant_ouvert', '125000.25')
            ->assertJsonPath('actions.creer', false);

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.budget.credits.store'), [
                'exercice_id' => $this->exercice->id,
                'rubrique_id' => BudgetRubrique::first()->id,
                'code' => 'CRD-SPA-X',
                'intitule' => 'Interdit',
                'montant_ouvert' => '10.00',
            ])
            ->assertForbidden();

        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $creation = $this->actingAs($gestionnaire)
            ->postJson(route('api.budget.credits.store'), [
                'exercice_id' => $this->exercice->id,
                'rubrique_id' => BudgetRubrique::first()->id,
                'code' => 'CRD-SPA-2',
                'intitule' => 'Deuxième ligne',
                'montant_ouvert' => '50000.50',
            ]);
        $creation->assertCreated()->assertJsonPath('ligne.montant_ouvert', '50000.50');
        $id = $creation->json('ligne.id');

        $this->actingAs($gestionnaire)
            ->putJson(route('api.budget.credits.update', $id), [
                'exercice_id' => $this->exercice->id,
                'rubrique_id' => BudgetRubrique::first()->id,
                'code' => 'CRD-SPA-2',
                'intitule' => 'Deuxième ligne',
                'montant_ouvert' => '90000.25',
            ])
            ->assertOk()
            ->assertJsonPath('ligne.montant_ouvert', '90000.25')
            ->assertJsonPath('ligne.disponible', '90000.25');

        $this->actingAs($gestionnaire)
            ->deleteJson(route('api.budget.credits.destroy', $id))
            ->assertForbidden();

        $this->actingAs($this->user('administrateur'))
            ->deleteJson(route('api.budget.credits.destroy', $id))
            ->assertOk();

        $this->assertSoftDeleted('lignes_budgetaires', ['id' => $id]);

        Budget::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'BUDGET-2033',
            'libelle' => 'Verrou',
            'statut' => 'adopte',
            'adopte_le' => now(),
            'montant_total' => 125000.25,
        ]);

        $this->actingAs($gestionnaire)
            ->postJson(route('api.budget.credits.store'), [
                'exercice_id' => $this->exercice->id,
                'rubrique_id' => BudgetRubrique::first()->id,
                'code' => 'CRD-SPA-3',
                'intitule' => 'Verrouillé',
                'montant_ouvert' => '10.00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');
    }
}
