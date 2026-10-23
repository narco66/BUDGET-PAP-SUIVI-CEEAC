<?php

namespace Tests\Feature;

use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\Strategie\Vision;
use App\Models\User;
use Database\Seeders\PlanificationStrategiqueSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StrategiePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P1 — routes CRUD, hub, exports et permissions UI. */
class PlanificationStrategiquePhaseP1Test extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, StrategiePermissionsSeeder::class]);
        $this->gestionnaire = User::factory()->create(['actif' => true]);
        $this->gestionnaire->assignRole('directeur');
    }

    public function test_hub_accessible_avec_permission(): void
    {
        $this->seed(PlanificationStrategiqueSeeder::class);

        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.hub'))
            ->assertOk()
            ->assertSee('Planification stratégique institutionnelle');
    }

    public function test_hub_interdit_sans_permission(): void
    {
        $lecteur = User::factory()->create(['actif' => true]);
        $lecteur->syncRoles([]);
        $lecteur->givePermissionTo('strategie.view');
        $lecteur->revokePermissionTo('strategie.view');

        $this->actingAs($lecteur)->get(route('strategie.hub'))->assertForbidden();
    }

    public function test_crud_vision_complet(): void
    {
        $this->actingAs($this->gestionnaire)->get(route('strategie.visions.index'))->assertOk();

        $this->actingAs($this->gestionnaire)->post(route('strategie.visions.store'), [
            'reference' => 'VIS-TEST',
            'intitule' => 'Vision test audit',
            'periode' => '2026-2036',
            'statut' => 'actif',
            'version' => 1,
        ])->assertRedirect();

        $vision = Vision::where('reference', 'VIS-TEST')->first();
        $this->assertNotNull($vision);

        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.visions.show', $vision))
            ->assertOk()
            ->assertSee('Vision test audit');

        $this->actingAs($this->gestionnaire)->put(route('strategie.visions.update', $vision), [
            'reference' => 'VIS-TEST',
            'intitule' => 'Vision test modifiée',
            'statut' => 'actif',
            'version' => 2,
        ])->assertRedirect();

        $this->assertSame('Vision test modifiée', $vision->fresh()->intitule);
    }

    public function test_creation_programme_requiert_orientation(): void
    {
        $this->seed(PlanificationStrategiqueSeeder::class);
        $orientation = OrientationStrategique::first();
        $user = User::factory()->create(['actif' => true]);

        $response = $this->actingAs($this->gestionnaire)->post(route('strategie.programmes.store'), [
            'orientation_strategique_id' => $orientation->id,
            'code' => 'PS-TEST',
            'intitule' => 'Programme test',
            'responsable_user_id' => $user->id,
            'statut' => 'actif',
            'budget_previsionnel' => 1000000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('programmes_strategiques', ['code' => 'PS-TEST']);
    }

    public function test_creation_projet_requiert_responsable(): void
    {
        $this->seed(PlanificationStrategiqueSeeder::class);
        $programme = ProgrammeStrategique::first();
        $user = User::factory()->create(['actif' => true]);

        $this->actingAs($this->gestionnaire)->post(route('strategie.projets.store'), [
            'programme_strategique_id' => $programme->id,
            'code' => 'PRJ-TEST',
            'intitule' => 'Projet test',
            'responsable_user_id' => $user->id,
            'statut' => 'planifie',
            'cycle_vie' => 'initiation',
        ])->assertRedirect();

        $this->assertDatabaseHas('projets', ['code' => 'PRJ-TEST', 'responsable_user_id' => $user->id]);
    }

    public function test_archivage_vision(): void
    {
        $vision = Vision::create([
            'reference' => 'VIS-ARCH',
            'intitule' => 'Vision archivage',
            'statut' => 'actif',
            'version' => 1,
        ]);

        $this->actingAs($this->gestionnaire)
            ->post(route('strategie.archiver', ['entite' => 'visions', 'id' => $vision->id]))
            ->assertRedirect();

        $this->assertSame('archive', $vision->fresh()->statut);
    }

    public function test_export_excel_programmes(): void
    {
        $this->seed(PlanificationStrategiqueSeeder::class);

        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.export.excel', 'programmes'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_pdf_synthese(): void
    {
        $this->seed(PlanificationStrategiqueSeeder::class);

        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_filtre_index_plans(): void
    {
        PlanStrategique::create([
            'reference' => 'PSC-FILTRE',
            'intitule' => 'Plan filtré actif',
            'date_debut' => '2026-01-01',
            'date_fin' => '2030-12-31',
            'statut' => 'actif',
            'version' => 1,
        ]);
        PlanStrategique::create([
            'reference' => 'PSC-BROUILLON',
            'intitule' => 'Plan brouillon',
            'date_debut' => '2026-01-01',
            'date_fin' => '2030-12-31',
            'statut' => 'brouillon',
            'version' => 1,
        ]);

        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.plans.index', ['statut' => 'actif']))
            ->assertOk()
            ->assertSee('PSC-FILTRE')
            ->assertDontSee('PSC-BROUILLON');
    }

    public function test_manuel_utilisateur_accessible_avec_permission(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.aide'))
            ->assertOk()
            ->assertSee('Manuel utilisateur')
            ->assertSee('Planification stratégique');

        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.aide.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.aide.word'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }
}
