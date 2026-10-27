<?php

namespace Tests\Feature;

use App\Models\Strategie\IndicateurStrategique;
use App\Models\Strategie\ObjectifStrategique;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\RisqueStrategique;
use App\Models\User;
use App\Services\Strategie\StrategieCoherenceService;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\PlanificationStrategiqueSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StrategiePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase P3 — objectifs, indicateurs, risques, cohérence, GED, validation. */
class PlanificationStrategiquePhaseP3Test extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;

    private User $validateur;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, StrategiePermissionsSeeder::class, ExerciceSeeder::class]);
        $this->gestionnaire = User::factory()->create(['actif' => true]);
        $this->gestionnaire->assignRole('directeur');
        $this->validateur = User::factory()->create(['actif' => true]);
        $this->validateur->assignRole('commissaire');
        $this->seed(PlanificationStrategiqueSeeder::class);
    }

    public function test_crud_objectif_strategique(): void
    {
        $plan = PlanStrategique::first();
        $orientation = OrientationStrategique::first();

        $this->actingAs($this->gestionnaire)->post(route('strategie.objectifs.store'), [
            'plan_strategique_id' => $plan->id,
            'orientation_strategique_id' => $orientation->id,
            'code' => 'OBJ-TEST-P3',
            'intitule' => 'Objectif test P3',
            'ordre' => 2,
            'statut' => 'actif',
        ])->assertRedirect();

        $this->assertDatabaseHas('objectifs_strategiques', ['code' => 'OBJ-TEST-P3']);
    }

    public function test_crud_indicateur_strategique(): void
    {
        $objectif = ObjectifStrategique::first();

        $this->actingAs($this->gestionnaire)->post(route('strategie.indicateurs.store'), [
            'objectif_strategique_id' => $objectif->id,
            'code' => 'IND-TEST-P3',
            'libelle' => 'Indicateur test P3',
            'unite' => '%',
            'periodicite' => 'annuel',
            'valeur_cible' => 75,
            'statut' => 'actif',
        ])->assertRedirect();

        $this->assertDatabaseHas('indicateurs_strategiques', ['code' => 'IND-TEST-P3']);
    }

    public function test_crud_risque_strategique(): void
    {
        $plan = PlanStrategique::first();

        $this->actingAs($this->gestionnaire)->post(route('strategie.risques.store'), [
            'plan_strategique_id' => $plan->id,
            'code' => 'RSK-TEST-P3',
            'description' => 'Risque test phase P3',
            'probabilite' => 'moyenne',
            'impact' => 'eleve',
            'mitigation' => 'Plan de mitigation test',
            'statut' => 'identifie',
        ])->assertRedirect();

        $this->assertDatabaseHas('risques_strategiques', ['code' => 'RSK-TEST-P3']);
    }

    public function test_rapport_coherence_accessible(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.rapport.coherence'))
            ->assertOk()
            ->assertSee('Rapport de cohérence stratégique')
            ->assertSee('Score global');
    }

    public function test_service_coherence_retourne_score(): void
    {
        $rapport = app(StrategieCoherenceService::class)->rapport();

        $this->assertArrayHasKey('score_global', $rapport);
        $this->assertGreaterThanOrEqual(0, $rapport['score_global']);
        $this->assertGreaterThan(0, $rapport['objectifs_total']);
    }

    public function test_workflow_validation_plan(): void
    {
        $plan = PlanStrategique::first();
        $plan->update(['statut' => 'brouillon', 'validated_at' => null, 'validated_by' => null]);

        $this->actingAs($this->gestionnaire)
            ->post(route('strategie.plans.soumettre', $plan))
            ->assertRedirect();

        $this->assertSame('en_validation', $plan->fresh()->statut);

        $this->actingAs($this->validateur)
            ->post(route('strategie.plans.valider', $plan))
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame('actif', $plan->statut);
        $this->assertNotNull($plan->validated_at);
        $this->assertSame($this->validateur->id, $plan->validated_by);
    }

    public function test_soumission_workflow_refuse_plan_incomplet(): void
    {
        // Circuit STRATEGIE actif : les préconditions (orientation + objectif) s'appliquent aussi.
        $this->seed(\Database\Seeders\WorkflowSeeder::class);
        $plan = PlanStrategique::first()->replicate(['validated_at', 'validated_by', 'submitted_at']);
        $plan->fill(['reference' => 'PSC-VIDE', 'intitule' => 'Plan sans contenu', 'statut' => 'brouillon'])->save();

        $this->actingAs($this->gestionnaire)
            ->post(route('strategie.plans.soumettre', $plan))
            ->assertRedirect()
            ->assertSessionHas('erreur');

        $this->assertSame('brouillon', $plan->fresh()->statut);
        $this->assertNull($plan->fresh()->workflowActif);
    }

    public function test_rejet_plan_strategique(): void
    {
        $plan = PlanStrategique::first();
        $plan->update(['statut' => 'en_validation']);

        $this->actingAs($this->validateur)
            ->post(route('strategie.plans.rejeter', $plan), ['motif_rejet' => 'Compléter les objectifs'])
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame('rejete', $plan->statut);
        $this->assertSame('Compléter les objectifs', $plan->motif_rejet);
    }

    public function test_ged_document_plan(): void
    {
        Storage::fake('local');
        $plan = PlanStrategique::first();

        $this->actingAs($this->gestionnaire)->post(route('strategie.documents.store', ['plan', $plan->id]), [
            'fichier' => UploadedFile::fake()->create('psc-officiel.pdf', 100, 'application/pdf'),
            'categorie' => 'document_officiel',
        ])->assertRedirect();

        $this->assertSame(1, $plan->fresh()->pieces()->count());
    }

    public function test_migration_validation_plan_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('plans_strategiques', 'submitted_at'));
        $this->assertTrue(Schema::hasColumn('plans_strategiques', 'validated_at'));
        $this->assertTrue(Schema::hasColumn('plans_strategiques', 'validated_by'));
    }

    public function test_export_coherence_pdf(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.export.coherence.pdf'))
            ->assertOk();
    }

    public function test_hub_phase_p3_kpis(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.hub'))
            ->assertOk()
            ->assertSee('Objectifs actifs')
            ->assertSee('Indicateurs actifs')
            ->assertSee('Risques ouverts');
    }
}
