<?php

namespace Tests\Feature;

use App\Models\Collecte;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Rapport;
use App\Models\Strategie\PlanStrategique;
use App\Models\User;
use App\Models\VisaElectronique;
use App\Models\Workflow;
use App\Services\WorkflowService;
use App\Support\WorkflowRejetCategorie;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase W-P2 — visas, convergence D14, dashboard. */
class WorkflowP2Test extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, WorkflowPermissionsSeeder::class, WorkflowSeeder::class]);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_table_visas_electroniques_existe(): void
    {
        $this->assertTrue(Schema::hasTable('visas_electroniques'));
    }

    public function test_circuits_p2_sont_seedes(): void
    {
        foreach (['RAPPORT', 'STRATEGIE', 'COLLECTE'] as $code) {
            $this->assertNotNull(Workflow::where('code', $code)->first());
        }
    }

    public function test_visa_emis_lors_d_une_validation(): void
    {
        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-VISA',
            'libelle' => 'PAP',
            'statut' => 'brouillon',
        ]);

        $instance = $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));
        $this->workflow->valider($instance, $this->user('chef_service'));

        $this->assertSame(1, VisaElectronique::count());
        $etape = $instance->fresh()->etapes()->where('ordre', 1)->first();
        $this->assertNotNull($etape->hash_visa);
    }

    public function test_circuit_collecte_deux_etapes(): void
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $collecte = Collecte::create([
            'campagne_id' => \App\Models\CampagneSuivi::create([
                'exercice_id' => $exercice->id,
                'libelle' => 'T1',
                'type' => 'trimestriel',
                'periode' => '2026-T1',
                'statut' => 'ouverte',
            ])->id,
            'activite_id' => $this->activiteId($exercice),
            'taux_physique' => 50,
            'montant_realise' => 1000,
            'statut' => 'saisie',
            'saisi_par' => $this->user('comptable')->id,
        ]);

        $instance = $this->workflow->demarrer($collecte, 'COLLECTE', $this->user('comptable'));
        $this->assertCount(2, $instance->etapes);

        $this->workflow->valider($instance->fresh(), $this->user('chef_service'));
        $this->assertSame('verifiee', $collecte->fresh()->statut);

        $collecte->pieces()->create([
            'nom' => 'j.pdf', 'chemin' => 't/j.pdf', 'type_mime' => 'application/pdf',
            'taille' => 100, 'categorie' => 'justificatif', 'televerse_par' => $this->user('comptable')->id,
        ]);

        $cf = $this->user('controleur_financier');
        $this->actingAs($cf);
        $this->workflow->valider($instance->fresh(), $cf);

        $this->assertSame('validee', $collecte->fresh()->statut);
    }

    public function test_circuit_rapport_validation_et_publication(): void
    {
        $rapport = Rapport::create([
            'reference' => 'RPT-WF-1',
            'type' => 'synthese',
            'titre' => 'Test',
            'format' => 'pdf',
            'qr_token' => 'qr-wf-test-1',
            'version' => 1,
            'statut' => 'genere',
            'statut_workflow' => 'genere',
            'genere_par' => $this->user('comptable')->id,
        ]);

        $instance = $this->workflow->demarrer($rapport, 'RAPPORT', $this->user('comptable'));
        $this->workflow->valider($instance->fresh(), $this->user('controleur_financier'));
        $this->assertSame('valide', $rapport->fresh()->statut_workflow);

        $sg = $this->user('secretaire_general');
        $this->actingAs($sg);
        $this->workflow->valider($instance->fresh(), $sg);

        $this->assertSame('publie', $rapport->fresh()->statut_workflow);
        $this->assertTrue($rapport->fresh()->verrouille);
    }

    public function test_plan_strategique_via_d14(): void
    {
        $plan = PlanStrategique::create([
            'reference' => 'PS-WF',
            'intitule' => 'Plan test',
            'statut' => 'brouillon',
            'date_debut' => '2026-01-01',
            'date_fin' => '2030-12-31',
        ]);

        $instance = $this->workflow->demarrer($plan, 'STRATEGIE', $this->user('directeur'));
        $this->assertSame('en_validation', $plan->fresh()->statut);

        $this->workflow->valider($instance->fresh(), $this->user('directeur'));
        $sg = $this->user('secretaire_general');
        $this->actingAs($sg);
        $this->workflow->valider($instance->fresh(), $sg);

        $this->assertSame('actif', $plan->fresh()->statut);
    }

    public function test_dashboard_workflow_accessible(): void
    {
        config(['mfa.enabled' => false]);   // MFA obligatoire pour le CF : hors du sujet de ce test

        $this->actingAs($this->user('controleur_financier'))
            ->get(route('workflow.dashboard'))
            ->assertOk()
            ->assertSee('Performance des circuits')
            ->assertSee('Goulots d\'étranglement par étape', false)
            ->assertSee('Respect des délais')
            ->assertSee('Dossiers en retard');
    }

    public function test_dashboard_workflow_mesure_le_delai_de_traitement_et_les_filtres(): void
    {
        config(['mfa.enabled' => false]);
        $service = app(\App\Services\Workflow\WorkflowDashboardService::class);
        $workflow = \App\Models\Workflow::where('code', 'ENGAGEMENT')->with('etapes')->firstOrFail();
        $instance = \App\Models\WorkflowInstance::query()->create([
            'workflow_id' => $workflow->id, 'objet_type' => 'test', 'objet_id' => 1,
            'statut' => 'valide', 'etape_courante' => 1, 'demarre_par' => $this->user('directeur')->id,
        ]);
        $etape = $instance->etapes()->create(['workflow_etape_id' => $workflow->etapes->first()->id, 'ordre' => 1, 'role' => 'directeur', 'statut' => 'valide']);
        $tache = \App\Models\WorkflowTache::query()->create([
            'reference' => 'TACHE-TEST-000001', 'workflow_instance_id' => $instance->id,
            'workflow_instance_etape_id' => $etape->id, 'workflow_code' => 'ENGAGEMENT', 'module' => 'ENG', 'objet_type' => 'test', 'objet_id' => 1,
            'etape_ordre' => 1, 'role' => 'directeur', 'statut' => 'terminee', 'decision' => 'valide',
            'echeance_le' => now()->addDay(), 'termine_le' => now(),
        ]);
        $tache->forceFill(['created_at' => now()->subHours(6)])->save();

        $tableau = $service->tableau(30, 'ENG');
        $this->assertEqualsWithDelta(6.0, $tableau['kpi']['delai_moyen_heures'], 0.1, 'Délai = activation → décision.');
        $this->assertSame(100.0, $tableau['kpi']['taux_sla_pct']);
        $this->assertSame(0, $service->tableau(30, 'PAY')['kpi']['decisions_periode'], 'Filtre par module appliqué.');

        $this->actingAs($this->user('controleur_financier'))
            ->get(route('workflow.dashboard', ['module' => 'ENG', 'jours' => 30]))->assertOk()->assertSee('6,0 h');
    }

    public function test_rejet_collecte_avec_categorie(): void
    {
        $exercice = Exercice::create(['annee' => 2027, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $collecte = Collecte::create([
            'campagne_id' => \App\Models\CampagneSuivi::create([
                'exercice_id' => $exercice->id,
                'libelle' => 'T2',
                'type' => 'trimestriel',
                'periode' => '2027-T2',
                'statut' => 'ouverte',
            ])->id,
            'activite_id' => $this->activiteId($exercice),
            'taux_physique' => 40,
            'montant_realise' => 500,
            'statut' => 'saisie',
            'saisi_par' => $this->user('comptable')->id,
        ]);

        $instance = $this->workflow->demarrer($collecte, 'COLLECTE', $this->user('comptable'));
        $this->workflow->rejeter(
            $instance,
            $this->user('chef_service'),
            'Données incohérentes.',
            WorkflowRejetCategorie::TECHNIQUE,
        );

        $collecte->refresh();
        $this->assertSame('saisie', $collecte->statut);
        $this->assertSame(WorkflowRejetCategorie::TECHNIQUE, $collecte->categorie_rejet);
    }

    private function activiteId(Exercice $exercice): int
    {
        $pilier = \App\Models\Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = \App\Models\Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $produit = \App\Models\Produit::create(['axe_id' => $axe->id, 'code' => 'PR', 'intitule' => 'PR']);
        $sp = \App\Models\SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP', 'intitule' => 'SP']);

        return \App\Models\Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $produit->id,
            'code' => 'ACT-1',
            'intitule' => 'Act',
            'montant_total' => 10000,
        ])->id;
    }
}
