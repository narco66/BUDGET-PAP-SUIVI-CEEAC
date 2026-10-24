<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetPreparation;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\BudgetPermissionsSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BudgetSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, BudgetPermissionsSeeder::class, WorkflowSeeder::class, NomenclatureBudgetaireSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2032, 'libelle' => 'Ex 2032', 'statut' => 'ouvert']);
        Notification::fake();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function rattacherLigne(): LigneBudgetaire
    {
        $dept = Structure::create([
            'code' => 'BUD-DEPT', 'libelle' => 'Département budget SPA', 'type' => 'departement',
            'parent_id' => null, 'niveau' => 0, 'ordre' => 1, 'actif' => true,
        ]);
        $direction = Structure::create([
            'code' => 'BUD-DIR', 'libelle' => 'Direction budget SPA', 'type' => 'direction',
            'parent_id' => $dept->id, 'niveau' => 1, 'ordre' => 1, 'actif' => true,
        ]);

        return LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'structure_id' => $direction->id,
            'rubrique_id' => BudgetRubrique::first()->id,
            'code' => 'BUD-SPA-1',
            'intitule' => 'Ligne enveloppe SPA',
            'montant_ouvert' => 125000.25,
        ]);
    }

    public function test_lecteur_liste_sans_pouvoir_creer_ni_soumettre(): void
    {
        $this->rattacherLigne();
        $budget = Budget::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'BUDGET-2032',
            'libelle' => 'Budget lecture',
            'statut' => 'brouillon',
            'montant_total' => 125000.25,
        ]);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.budgets.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reference', 'BUDGET-2032')
            ->assertJsonPath('data.0.montant', '125000.25')
            ->assertJsonPath('data.0.actions.soumettre', false);

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.budgets.store'), [
                'exercice_id' => $this->exercice->id,
                'libelle' => 'Interdit',
            ])
            ->assertForbidden();

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.budgets.soumettre', $budget))
            ->assertForbidden();
    }

    public function test_parcours_api_creation_soumission_et_premiere_etape(): void
    {
        $this->rattacherLigne();
        $directeur = $this->user('directeur');
        $chef = $this->user('chef_service');

        $this->actingAs($directeur)
            ->getJson(route('api.budgets.options'))
            ->assertOk()
            ->assertJsonPath('reference_suggestion', 'BUDGET-2032')
            ->assertJsonPath('syntheses.'.$this->exercice->id.'.depenses', '125000.25');

        $this->actingAs($directeur)->postJson(route('api.budgets.store'), [
            'exercice_id' => $this->exercice->id,
            'libelle' => 'Budget institutionnel 2032',
            'reference' => 'BUDGET-2032-SPA',
        ])->assertCreated()
            ->assertJsonPath('budget.statut', 'brouillon')
            ->assertJsonPath('budget.montant', '125000.25');

        $budget = Budget::firstWhere('reference', 'BUDGET-2032-SPA');

        $this->actingAs($directeur)->putJson(route('api.budgets.update', $budget), [
            'libelle' => 'Budget institutionnel 2032 (à jour)',
        ])->assertOk()->assertJsonPath('budget.libelle', 'Budget institutionnel 2032 (à jour)');

        $soumission = $this->actingAs($directeur)
            ->withHeaders(['Idempotency-Key' => 'budget-soumettre-1'])
            ->postJson(route('api.budgets.soumettre', $budget->fresh()));

        if ($soumission->status() === 422) {
            $soumission->assertJsonPath('code', 'audit_bloquant');

            return;
        }

        $soumission->assertOk()->assertJsonPath('budget.statut', 'en_validation');

        $this->actingAs($directeur)
            ->withHeaders(['Idempotency-Key' => 'budget-soumettre-1'])
            ->postJson(route('api.budgets.soumettre', $budget->fresh()))
            ->assertOk()
            ->assertJsonPath('code', 'soumis');

        $this->actingAs($chef)
            ->postJson(route('api.budgets.decisions', $budget->fresh()), ['decision' => 'valider'])
            ->assertOk()
            ->assertJsonPath('budget.statut', 'en_validation');
    }

    public function test_revision_apres_adoption(): void
    {
        $directeur = $this->user('directeur');
        $budget = Budget::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'BUDGET-2032-R',
            'libelle' => 'Budget adopté',
            'statut' => 'adopte',
            'adopte_le' => now(),
        ]);

        $this->actingAs($directeur)
            ->postJson(route('api.budgets.revision', $budget), ['motif' => 'Rectificatif Q2'])
            ->assertOk()
            ->assertJsonPath('budget.statut', 'en_revision');
    }

    public function test_preparation_arbitrage_et_consolidation_sans_ieee(): void
    {
        $ligne = $this->rattacherLigne();
        $budget = Budget::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'BUDGET-2032-P',
            'libelle' => 'Budget préparation',
            'statut' => 'brouillon',
            'montant_total' => 125000.25,
        ]);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.budgets.preparations', $budget))
            ->assertOk()
            ->assertJsonPath('actions.preparer', false)
            ->assertJsonPath('synthese.montant_demande', '0.00');

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.budgets.preparations.initialiser', $budget))
            ->assertForbidden();

        $directeur = $this->user('directeur');
        $this->actingAs($directeur)
            ->postJson(route('api.budgets.preparations.initialiser', $budget))
            ->assertOk()
            ->assertJsonPath('code', 'initialise');

        $this->actingAs($directeur)
            ->getJson(route('api.budgets.preparations', $budget))
            ->assertOk()
            ->assertJsonPath('preparations.0.montant_demande', '125000.25')
            ->assertJsonPath('actions.preparer', true);

        $prep = BudgetPreparation::firstWhere('budget_id', $budget->id);
        $this->actingAs($directeur)
            ->putJson(route('api.budgets.preparations.update', [$budget, $prep]), [
                'montant_demande' => '99000.50',
                'montant_propose' => '99000.50',
            ])
            ->assertOk()
            ->assertJsonPath('preparation.montant_demande', '99000.50');

        $this->actingAs($directeur)
            ->postJson(route('api.budgets.preparations.soumettre', $budget), [
                'structure_id' => $ligne->structure_id,
            ])
            ->assertOk()
            ->assertJsonPath('code', 'soumis');

        $this->actingAs($directeur)
            ->postJson(route('api.budgets.preparations.valider', $budget), ['niveau' => 'direction'])
            ->assertOk()
            ->assertJsonPath('niveau', 'direction');

        $this->actingAs($this->user('commissaire'))
            ->postJson(route('api.budgets.preparations.valider', $budget), ['niveau' => 'departement'])
            ->assertOk();

        $cf = $this->user('controleur_financier');
        $this->actingAs($cf)
            ->postJson(route('api.budgets.preparations.valider', $budget), ['niveau' => 'dppb'])
            ->assertOk();

        $this->actingAs($cf)
            ->postJson(route('api.budgets.preparations.arbitrer', $budget), [
                'preparation_id' => $prep->id,
                'montant_apres' => '88000.25',
                'motif' => 'Ajustement DPPB SPA',
            ])
            ->assertOk()
            ->assertJsonPath('preparation.montant_arbitre', '88000.25');

        $this->actingAs($directeur)
            ->postJson(route('api.budgets.preparations.consolider', $budget))
            ->assertOk()
            ->assertJsonPath('code', 'consolide')
            ->assertJsonPath('montant_total', '88000.25');
    }

    public function test_simulations_comparaison_et_retenir_sans_ieee(): void
    {
        $this->rattacherLigne();
        $budget = Budget::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'BUDGET-2032-S',
            'libelle' => 'Budget simulations',
            'statut' => 'brouillon',
            'montant_total' => 125000.25,
        ]);
        $directeur = $this->user('directeur');
        $this->actingAs($directeur)
            ->postJson(route('api.budgets.preparations.initialiser', $budget))
            ->assertOk();

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.budgets.simulations', $budget))
            ->assertOk()
            ->assertJsonPath('actions.simuler', false);

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.budgets.simulations.store', $budget), [
                'libelle' => 'Interdit',
                'type' => 'global_pct',
                'taux' => -5,
            ])
            ->assertForbidden();

        $moins = $this->actingAs($directeur)->postJson(route('api.budgets.simulations.store', $budget), [
            'libelle' => 'Réduction 5 %',
            'type' => 'global_pct',
            'taux' => -5,
        ]);
        $moins->assertCreated()
            ->assertJsonPath('simulation.montant_base', '125000.25')
            ->assertJsonPath('simulation.montant_simule', '118750.24')
            ->assertJsonPath('simulation.ecart_pct', '-5.00');

        $plus = $this->actingAs($directeur)->postJson(route('api.budgets.simulations.store', $budget), [
            'libelle' => 'Hausse 10 %',
            'type' => 'global_pct',
            'taux' => 10,
        ]);
        $plus->assertCreated()->assertJsonPath('simulation.ecart_pct', '10.00');

        $idA = $moins->json('simulation.id');
        $idB = $plus->json('simulation.id');

        $this->actingAs($directeur)
            ->getJson(route('api.budgets.simulations', ['budget' => $budget, 'comparer' => [$idA, $idB]]))
            ->assertOk()
            ->assertJsonPath('comparaison.scenarios.0.montant_base', '125000.25')
            ->assertJsonCount(2, 'comparaison.scenarios');

        $this->actingAs($directeur)
            ->postJson(route('api.budgets.simulations.retenir', [$budget, $idA]))
            ->assertOk()
            ->assertJsonPath('simulation.statut', 'retenu');
    }

    public function test_revisions_rectificatif_et_virement_sans_ieee(): void
    {
        $ligneA = $this->rattacherLigne();
        $ligneA->update(['montant_ouvert' => 100000.25, 'montant_engage' => 0]);
        $ligneB = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'structure_id' => $ligneA->structure_id,
            'rubrique_id' => $ligneA->rubrique_id,
            'code' => 'BUD-SPA-2',
            'intitule' => 'Ligne enveloppe SPA 2',
            'montant_ouvert' => 50000.50,
            'montant_engage' => 0,
        ]);
        $budget = Budget::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'BUDGET-2032-REV',
            'libelle' => 'Budget révisions',
            'statut' => 'adopte',
            'adopte_le' => now(),
            'montant_total' => 150000.75,
        ]);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.budgets.revisions', $budget))
            ->assertOk()
            ->assertJsonPath('actions.rectifier', false)
            ->assertJsonPath('rapprochement.montant_budget', '150000.75');

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.budgets.revisions.rectificatif', $budget), [
                'ligne_budgetaire_id' => $ligneA->id,
                'montant_apres' => '90000.25',
                'motif' => 'Interdit',
            ])
            ->assertForbidden();

        $directeur = $this->user('directeur');
        $this->actingAs($directeur)
            ->postJson(route('api.budgets.revisions.rectificatif', $budget), [
                'ligne_budgetaire_id' => $ligneA->id,
                'montant_apres' => '90000.25',
                'motif' => 'Ajustement post-adoption SPA',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'valide')
            ->assertJsonPath('revision.mouvements.0.montant_apres', '90000.25');

        $this->assertSame('90000.25', \App\Support\Montant::api($ligneA->fresh()->montant_revise));

        $this->actingAs($directeur)
            ->postJson(route('api.budgets.revisions.virement', $budget), [
                'ligne_source_id' => $ligneA->id,
                'ligne_destination_id' => $ligneB->id,
                'montant' => '10000.25',
                'motif' => 'Virement SPA',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'valide');
    }

    public function test_rapport_synthese_sans_ieee(): void
    {
        $this->rattacherLigne();
        $budget = Budget::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'BUDGET-2032-RPT',
            'libelle' => 'Budget rapport',
            'statut' => 'brouillon',
            'montant_total' => 125000.25,
        ]);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.budgets.rapport', $budget))
            ->assertOk()
            ->assertJsonPath('synthese.montants.ouvert', '125000.25')
            ->assertJsonPath('synthese.montants.revise', '125000.25')
            ->assertJsonPath('synthese.enveloppe.montant', '125000.25')
            ->assertJsonPath('synthese.enveloppe.montant_total', '125000.25')
            ->assertJsonPath('synthese.preparation.montant_demande', '0.00')
            ->assertJsonPath('synthese.conformite.checks.enveloppe_creee', true);
    }
}
