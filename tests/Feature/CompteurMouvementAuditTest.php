<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Liquidation;
use App\Models\User;
use App\Services\Budget\CompteurMouvementAuditService;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Audit sémantique des mouvements de compteurs (consolidation point 6). */
class CompteurMouvementAuditTest extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private Exercice $exercice;

    private LigneBudgetaire $ligne;

    private \App\Models\Activite $activite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(DepensePermissionsSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);

        [$this->activite, $this->ligne] = $this->activiteEtLigne($this->exercice, 'LB-AUDIT', 1000);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_chaine_coherente_sans_anomalie(): void
    {
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $this->activite, $this->ligne, $user, 600, 'EB-AUDIT');
        $svc = app(EngagementService::class);
        $engagement = $svc->creer(
            [
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'type' => 'simple',
                'objet' => 'Test',
            ],
            [['ligne_budgetaire_id' => $this->ligne->id, 'montant' => 600]],
            $user,
        );
        $svc->soumettre($engagement, $this->user('gestionnaire_budgetaire'));
        app(WorkflowService::class)->valider($engagement->workflowActif, $this->user('controleur_financier'));

        $liq = Liquidation::create([
            'numero' => 'LIQ-AUDIT', 'engagement_id' => $engagement->id,
            'montant' => 600, 'statut' => 'brouillon',
        ]);
        app(WorkflowService::class)->demarrer($liq, 'LIQUIDATION', $this->user('gestionnaire_budgetaire'));
        app(WorkflowService::class)->valider($liq->workflowActif, $this->user('controleur_financier'));

        $rapport = app(CompteurMouvementAuditService::class)->analyser($this->exercice->id);

        $this->assertSame(0, $rapport['total_anomalies']);
    }

    public function test_detecte_liquidation_validee_sans_compteur(): void
    {
        $engagement = Engagement::create([
            'numero' => 'ENG-X', 'exercice_id' => $this->exercice->id,
            'type' => 'simple', 'objet' => 'X', 'montant_total' => 500,
            'statut' => 'vise', 'credits_reserves' => true,
        ]);
        $engagement->imputations()->create(['ligne_budgetaire_id' => $this->ligne->id, 'montant' => 500]);
        $this->ligne->update(['montant_engage' => 500]);

        Liquidation::create([
            'numero' => 'LIQ-X', 'engagement_id' => $engagement->id,
            'montant' => 500, 'statut' => 'valide', 'compteurs_appliques' => false,
        ]);

        $rapport = app(CompteurMouvementAuditService::class)->analyser($this->exercice->id);

        $this->assertGreaterThan(0, $rapport['total_anomalies']);
        $this->assertTrue(
            collect($rapport['anomalies_semantiques'])->contains('type', 'ETAPE_VALIDE_SANS_COMPTEUR')
        );
    }

    public function test_detecte_ecart_compteur_ligne(): void
    {
        $engagement = Engagement::create([
            'numero' => 'ENG-Y', 'exercice_id' => $this->exercice->id,
            'type' => 'simple', 'objet' => 'Y', 'montant_total' => 400,
            'statut' => 'vise', 'credits_reserves' => true,
        ]);
        $engagement->imputations()->create(['ligne_budgetaire_id' => $this->ligne->id, 'montant' => 400]);
        $this->ligne->update(['montant_engage' => 999]);

        $rapport = app(CompteurMouvementAuditService::class)->analyser($this->exercice->id);

        $this->assertTrue(
            collect($rapport['ecarts_compteurs'])->contains(fn ($e) => $e['colonne'] === 'montant_engage')
        );
    }

    public function test_commande_audit_compteurs(): void
    {
        $this->artisan('budget:audit-compteurs', ['--exercice' => 2026])
            ->assertSuccessful();
    }
}
