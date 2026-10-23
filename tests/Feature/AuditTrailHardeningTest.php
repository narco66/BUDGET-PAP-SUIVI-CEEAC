<?php

namespace Tests\Feature;

use App\Models\ExpressionBesoin;
use App\Models\JournalAudit;
use App\Models\User;
use App\Services\WorkflowService;
use App\Support\AuditLogger;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Lot E — durcissement de la piste d'audit : rôle figé + journalisation des violations SoD. */
class AuditTrailHardeningTest extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private \App\Models\Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->exercice = \App\Models\Exercice::firstWhere('annee', 2026) ?? \App\Models\Exercice::first();
    }

    public function test_le_role_de_l_acteur_est_fige_dans_le_journal(): void
    {
        $user = User::create([
            'name' => 'Gestion', 'email' => 'g.role@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $user->assignRole('gestionnaire_budgetaire');

        $this->actingAs($user);
        AuditLogger::enregistrer('action_test');

        $this->assertDatabaseHas('journaux_audit', [
            'action' => 'action_test', 'user_nom' => 'Gestion', 'role' => 'gestionnaire_budgetaire',
        ]);
    }

    public function test_violation_de_separation_des_fonctions_est_journalisee(): void
    {
        // Un seul acteur cumule deux rôles consécutifs du circuit BESOIN (chef_service → directeur).
        $acteur = User::create([
            'name' => 'Cumul Rôles', 'email' => 'cumul@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $acteur->assignRole(['chef_service', 'directeur']);
        $this->actingAs($acteur);

        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-SOD', 1000);
        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-SOD-1', 'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id, 'ligne_budgetaire_id' => $ligne->id,
            'objet' => 'Besoin test SoD', 'montant_estime' => 500, 'statut' => 'brouillon',
        ]);

        $workflow = app(WorkflowService::class);
        $instance = $workflow->demarrer($besoin, 'BESOIN', $acteur);

        // Étape 1 (chef_service) validée par l'acteur.
        $workflow->traiterDecision($instance->fresh(), $acteur, 'valider');

        // Étape 2 (directeur) tentée par le MÊME acteur → bloquée + journalisée.
        try {
            $workflow->traiterDecision($besoin->fresh()->workflowActif, $acteur, 'valider');
            $this->fail('La séparation des fonctions aurait dû bloquer la 2ᵉ validation.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Séparation des fonctions', $e->getMessage());
        }

        $this->assertDatabaseHas('journaux_audit', ['action' => 'sod_violation']);
        $violation = JournalAudit::where('action', 'sod_violation')->latest('id')->first();
        $this->assertSame('chef_service, directeur', $violation->role); // rôle figé de l'acteur
    }
}
