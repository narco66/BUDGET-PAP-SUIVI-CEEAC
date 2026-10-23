<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Strategie\AlignementStrategique;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProgrammationPluriannuelle;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\User;
use App\Services\Strategie\StrategieAlignementService;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\Pap2026Seeder;
use Database\Seeders\PlanificationStrategiqueSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StrategiePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase P2 — programmations, matrice, alignements, pont RBM. */
class PlanificationStrategiquePhaseP2Test extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            StrategiePermissionsSeeder::class,
            ExerciceSeeder::class,
            Pap2026Seeder::class,
        ]);
        $this->gestionnaire = User::factory()->create(['actif' => true]);
        $this->gestionnaire->assignRole('directeur');
        $this->seed(PlanificationStrategiqueSeeder::class);
    }

    public function test_matrice_alignement_accessible(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.matrice'))
            ->assertOk()
            ->assertSee('Matrice d\'alignement stratégique');
    }

    public function test_service_matrice_contient_programme_demo(): void
    {
        $lignes = app(StrategieAlignementService::class)->matriceAlignement();
        $this->assertTrue($lignes->contains(fn ($l) => $l['programme'] === 'PS-INT-ECO'));
    }

    public function test_crud_programmation_pluriannuelle(): void
    {
        $plan = OrientationStrategique::first()->planStrategique;

        $this->actingAs($this->gestionnaire)->post(route('strategie.programmations-pluri.store'), [
            'plan_strategique_id' => $plan->id,
            'reference' => 'PROG-TEST-P2',
            'annee_debut' => 2026,
            'annee_fin' => 2030,
            'statut' => 'actif',
            'version' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('programmations_pluriannuelles', ['reference' => 'PROG-TEST-P2']);
    }

    public function test_crud_programmation_annuelle(): void
    {
        $pluri = ProgrammationPluriannuelle::first();
        $exercice = \App\Models\Exercice::query()
            ->whereNotIn('id', $pluri->programmationsAnnuelles()->pluck('exercice_id'))
            ->first() ?? \App\Models\Exercice::first();

        $this->actingAs($this->gestionnaire)->post(route('strategie.programmations-annuelles.store'), [
            'programmation_pluriannuelle_id' => $pluri->id,
            'exercice_id' => $exercice->id,
            'statut' => 'actif',
            'activites_prevues' => 'Test activités',
        ])->assertRedirect();

        $this->assertDatabaseHas('programmations_annuelles', [
            'programmation_pluriannuelle_id' => $pluri->id,
            'exercice_id' => $exercice->id,
        ]);
    }

    public function test_creation_alignement_strategique(): void
    {
        $programme = ProgrammeStrategique::first();

        $this->actingAs($this->gestionnaire)->post(route('strategie.alignements.store'), [
            'vision_id' => $programme->orientationStrategique->vision_id,
            'orientation_strategique_id' => $programme->orientation_strategique_id,
            'plan_strategique_id' => $programme->orientationStrategique->plan_strategique_id,
            'alignable_type' => ProgrammeStrategique::class,
            'alignable_id' => $programme->id,
            'niveau_alignement' => 'direct',
            'commentaire' => 'Test alignement P2',
        ])->assertRedirect(route('strategie.alignements.index'));

        $this->assertDatabaseHas('alignements_strategiques', [
            'alignable_type' => ProgrammeStrategique::class,
            'alignable_id' => $programme->id,
            'niveau_alignement' => 'direct',
        ]);
    }

    public function test_pont_rbm_lie_pilier_orientation(): void
    {
        $pilier = Pilier::first();
        $orientation = OrientationStrategique::first();
        $programme = ProgrammeStrategique::first();

        $this->actingAs($this->gestionnaire)->put(route('strategie.pont-rbm.update', $pilier), [
            'orientation_strategique_id' => $orientation->id,
            'programme_strategique_id' => $programme->id,
        ])->assertRedirect();

        $this->assertSame($orientation->id, $pilier->fresh()->orientation_strategique_id);
        $this->assertSame($pilier->id, $programme->fresh()->pilier_id);
    }

    public function test_programme_strategique_synchronise_pont_rbm(): void
    {
        $programme = ProgrammeStrategique::first();
        $pilier = Pilier::first();

        $this->actingAs($this->gestionnaire)->put(route('strategie.programmes.update', $programme), [
            'orientation_strategique_id' => $programme->orientation_strategique_id,
            'code' => $programme->code,
            'intitule' => $programme->intitule,
            'pilier_id' => $pilier->id,
            'statut' => 'actif',
            'budget_previsionnel' => $programme->budget_previsionnel,
        ])->assertRedirect();

        $this->assertSame($programme->orientation_strategique_id, $pilier->fresh()->orientation_strategique_id);
    }

    public function test_export_matrice_excel(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.export.excel', 'matrice'))
            ->assertOk();
    }

    public function test_hub_enrichi_phase_p2(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('strategie.hub'))
            ->assertOk()
            ->assertSee('Cohérence stratégique')
            ->assertSee('Programmation');
    }

    public function test_migration_pont_activite_projet(): void
    {
        $this->assertTrue(Schema::hasColumn('activites', 'projet_id'));

        $projet = ProjetStrategique::first();
        $this->assertNotNull($projet, 'Le seeder stratégique doit créer un projet après initialisation utilisateur.');

        $activite = Activite::query()->first() ?? $this->creerActiviteRbm();

        app(StrategieAlignementService::class)->rattacherActiviteProjet($activite, $projet->id);

        $this->assertSame($projet->id, $activite->fresh()->projet_id);
    }

    private function creerActiviteRbm(): Activite
    {
        $pilier = Pilier::first() ?? Pilier::create([
            'exercice_id' => \App\Models\Exercice::first()->id,
            'code' => 'P-TEST',
            'intitule' => 'Pilier test',
            'ordre' => 1,
        ]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-TEST', 'intitule' => 'Axe test', 'ordre' => 1]);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-TEST', 'intitule' => 'Produit test', 'ordre' => 1]);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP-TEST', 'intitule' => 'SP test', 'ordre' => 1]);

        return Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $produit->id,
            'code' => 'ACT-TEST-P2',
            'intitule' => 'Activité test P2',
            'montant_total' => 0,
            'ordre' => 1,
        ]);
    }
}
