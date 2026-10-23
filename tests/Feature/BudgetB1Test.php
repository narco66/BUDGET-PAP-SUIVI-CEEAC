<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Notifications\Budget\BudgetWorkflowNotification;
use App\Services\Budget\BudgetCompletenessService;
use App\Services\Budget\ExerciceService;
use App\Services\WorkflowService;
use Database\Seeders\Budget2026Seeder;
use Database\Seeders\BudgetB0Seeder;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\Pap2026Seeder;
use Database\Seeders\PapP0Seeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SourceFinancementSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/** Phase B1 — enveloppe budget, workflow adoption, verrouillage lignes. */
class BudgetB1Test extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            StructureSeeder::class,
            ExerciceSeeder::class,
            SourceFinancementSeeder::class,
            NomenclatureBudgetaireSeeder::class,
            Pap2026Seeder::class,
            Budget2026Seeder::class,
            WorkflowSeeder::class,
        ]);
        $this->seed(PapP0Seeder::class);
        $this->seed(BudgetB0Seeder::class);
        $this->directeur = User::factory()->create(['actif' => true])->assignRole('directeur');
    }

    public function test_creer_enveloppe_via_http(): void
    {
        $exercice = Exercice::create(['annee' => 2028, 'libelle' => 'Ex 2028', 'statut' => 'ouvert']);

        $this->actingAs($this->directeur)
            ->post(route('budget.enveloppe.store'), [
                'exercice_id' => $exercice->id,
                'libelle' => 'Budget institutionnel 2028',
                'reference' => 'BUDGET-2028',
            ])
            ->assertRedirect();

        $budget = Budget::firstWhere('exercice_id', $exercice->id);
        $this->assertNotNull($budget);
        $this->assertSame('brouillon', $budget->statut);
    }

    public function test_workflow_adoption_six_etapes(): void
    {
        $budget = $this->creerBudget();
        $instance = app(WorkflowService::class)->demarrer($budget, 'BUDGET', $this->directeur);

        $this->assertCount(6, $instance->etapes);

        foreach (['chef_service', 'directeur', 'commissaire', 'controleur_financier', 'secretaire_general', 'president'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
            app(WorkflowService::class)->valider($instance->fresh(), $user);
        }

        $budget->refresh();
        $this->assertSame('adopte', $budget->statut);
        $this->assertNotNull($budget->adopte_le);
        $this->assertTrue(
            LigneBudgetaire::where('exercice_id', $budget->exercice_id)->where('budget_id', $budget->id)->exists()
        );
    }

    public function test_verrou_lignes_apres_soumission(): void
    {
        $budget = $this->creerBudget();
        app(WorkflowService::class)->demarrer($budget, 'BUDGET', $this->directeur);

        $this->assertTrue(app(ExerciceService::class)->budgetVerrouille($budget->exercice_id));

        $ligne = LigneBudgetaire::where('exercice_id', $budget->exercice_id)->first();
        $this->expectException(RuntimeException::class);
        app(ExerciceService::class)->assertBudgetModifiable($budget->exercice_id);
    }

    public function test_revision_deverrouille_lignes(): void
    {
        $budget = $this->creerBudget(['statut' => 'adopte', 'adopte_le' => now()]);

        app(\App\Services\Budget\BudgetReferentielService::class)
            ->demanderRevision($budget, $this->directeur->id, 'Rectificatif Q2');

        $this->assertSame('en_revision', $budget->fresh()->statut);
        $this->assertFalse(app(ExerciceService::class)->budgetVerrouille($budget->exercice_id));
    }

    public function test_notification_soumission_budget(): void
    {
        Notification::fake();
        $chef = $this->user('chef_service');
        $budget = $this->creerBudget();
        app(BudgetCompletenessService::class)->completer($budget->exercice_id);

        $this->actingAs($this->directeur)
            ->post(route('budget.enveloppe.soumettre', $budget))
            ->assertRedirect()
            ->assertSessionHas('succes');

        Notification::assertSentTo($chef, BudgetWorkflowNotification::class);
    }

    public function test_suppression_interdite_si_adopte(): void
    {
        $budget = $this->creerBudget(['statut' => 'adopte']);

        $this->actingAs($this->directeur)
            ->delete(route('budget.enveloppe.destroy', $budget))
            ->assertForbidden();
    }

    private function creerBudget(array $attrs = []): Budget
    {
        $exercice = Exercice::firstWhere('annee', 2026);

        return Budget::updateOrCreate(
            ['exercice_id' => $exercice->id],
            array_merge([
                'reference' => 'BUDGET-'.$exercice->annee,
                'libelle' => 'Budget test',
                'statut' => 'brouillon',
                'date_elaboration' => now()->toDateString(),
            ], $attrs)
        );
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }
}
