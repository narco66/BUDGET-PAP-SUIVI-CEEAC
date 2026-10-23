<?php

namespace Tests\Feature;

use App\Models\Alerte;
use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\RelanceRule;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Alertes\AlerteBudgetDetector;
use App\Services\Alertes\AlerteRegistryService;
use App\Services\Alertes\RelanceMetierService;
use App\Services\WorkflowService;
use Database\Seeders\NotificationsAlertesSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase N-P1 — alertes persistées, relances métier, dashboard. */
class NotificationsAlertesP1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            NotificationsAlertesSeeder::class,
            WorkflowPermissionsSeeder::class,
            WorkflowSeeder::class,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_tables_p1_existent(): void
    {
        foreach (['alertes', 'relance_rules', 'relance_logs'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }

    public function test_registre_alerte_deduplique(): void
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $registry = app(AlerteRegistryService::class);

        Notification::fake();

        $a1 = $registry->enregistrer('budget', 'sans_enveloppe', 'Test', 'Message 1', exerciceId: $exercice->id);
        $a2 = $registry->enregistrer('budget', 'sans_enveloppe', 'Test', 'Message 2', exerciceId: $exercice->id);

        $this->assertSame($a1->id, $a2->id);
        $this->assertSame(1, Alerte::count());
        $this->assertSame('Message 2', $a2->fresh()->message);
    }

    public function test_detecteur_budget_depassement(): void
    {
        Notification::fake();

        $exercice = Exercice::create(['annee' => 2027, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $rub = BudgetRubrique::create([
            'paragraphe_id' => BudgetParagraphe::create([
                'article_id' => BudgetArticle::create([
                    'titre_id' => BudgetTitre::create(['sens' => 'depense', 'code' => '2', 'intitule' => 'B'])->id,
                    'code' => '24', 'intitule' => 'A',
                ])->id,
                'code' => '241', 'intitule' => 'P',
            ])->id,
            'code' => '241000', 'intitule' => 'R',
        ]);
        LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rub->id,
            'code' => 'LB-01',
            'libelle' => 'Ligne test',
            'montant_ouvert' => 1000,
            'montant_engage' => 1500,
            'montant_paye' => 0,
        ]);

        $count = app(AlerteBudgetDetector::class)->detecter($exercice->id);

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertDatabaseHas('alertes', [
            'module' => 'budget',
            'code' => 'depassement_credit',
            'statut' => 'ouverte',
        ]);
    }

    public function test_relance_metier_workflow_en_retard(): void
    {
        Notification::fake();

        $wf = Workflow::where('code', 'ENGAGEMENT')->firstOrFail();
        $wf->etapes()->update(['delai_jours' => 1]);

        $engagement = \App\Models\Engagement::create([
            'exercice_id' => Exercice::create(['annee' => 2028, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'numero' => 'ENG-REL-P1',
            'objet' => 'Test',
            'type' => 'simple',
            'montant_total' => 500,
            'statut' => 'brouillon',
            'createur_id' => $this->user('gestionnaire_budgetaire')->id,
        ]);

        $instance = app(WorkflowService::class)->demarrer($engagement, 'ENGAGEMENT', $this->user('gestionnaire_budgetaire'));
        $instance->etapeCourante()?->update(['echeance_le' => now()->subDays(2)]);

        $this->user('controleur_financier');

        $this->assertGreaterThan(0, RelanceRule::where('code', 'depense.engagement_attente')->count());

        $count = app(RelanceMetierService::class)->executer();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertDatabaseHas('relance_logs', ['module' => 'depense']);
    }

    public function test_dashboard_alertes_et_resolution(): void
    {
        Notification::fake();

        $cf = $this->user('controleur_financier');
        $alerte = app(AlerteRegistryService::class)->enregistrer(
            'budget',
            'test_dashboard',
            'Alerte test dashboard',
            'Message test',
            exerciceId: Exercice::create(['annee' => 2029, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
        );

        $this->actingAs($cf)
            ->get(route('alertes.index'))
            ->assertOk()
            ->assertSee('Alerte test dashboard')
            ->assertSee('Ouvertes');

        $this->actingAs($cf)
            ->post(route('alertes.resoudre', $alerte))
            ->assertRedirect();

        $this->assertSame('resolue', $alerte->fresh()->statut);
    }

    public function test_commande_detecter_budget(): void
    {
        $this->artisan('alertes:detecter-budget')->assertSuccessful();
    }

    public function test_bridge_performance_cree_alerte(): void
    {
        Notification::fake();

        $exercice = Exercice::create(['annee' => 2030, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $ap = \App\Models\AlertePerformance::create([
            'exercice_id' => $exercice->id,
            'type' => 'sous_performance_physique',
            'gravite' => 'elevee',
            'reference' => 'ACT-X',
            'message' => 'Test performance',
            'detecte_le' => now(),
        ]);

        app(AlerteRegistryService::class)->synchroniserDepuisPerformance($ap);

        $this->assertDatabaseHas('alertes', [
            'module' => 'suivi',
            'code' => 'performance.sous_performance_physique',
            'statut' => 'ouverte',
        ]);
    }
}
