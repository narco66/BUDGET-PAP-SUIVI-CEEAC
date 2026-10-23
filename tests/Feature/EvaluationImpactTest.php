<?php

namespace Tests\Feature;

use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Suivi\SuiviEvaluationService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuiviEvaluationPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P1 — Évaluations d'impact (S&E) : nouveau type « impact » dans EvaluationPeriodique (enum étendu),
 * généré via le socle de scoring S&E existant, sans nouvelle entité.
 */
class EvaluationImpactTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SuiviEvaluationPermissionsSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    public function test_enum_accepte_le_type_impact(): void
    {
        $eval = EvaluationPeriodique::create([
            'exercice_id' => $this->exercice->id,
            'periode' => 'IMP-2026',
            'type' => 'impact',
            'score_global' => 50,
            'niveau' => 'partiel',
        ]);

        $this->assertDatabaseHas('evaluations_periodiques', ['id' => $eval->id, 'type' => 'impact']);
    }

    public function test_service_genere_une_evaluation_impact(): void
    {
        $eval = app(SuiviEvaluationService::class)->genererEvaluationImpact($this->exercice->id);

        $this->assertSame('impact', $eval->type);
        $this->assertSame('IMP-2026', $eval->periode);
        $this->assertNull($eval->campagne_id);
    }

    public function test_endpoint_generer_impact_avec_permission(): void
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo('suivi.valider');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user)
            ->post(route('suivi-evaluation.generer-impact'), ['exercice' => $this->exercice->id])
            ->assertRedirect();

        $this->assertDatabaseHas('evaluations_periodiques', [
            'exercice_id' => $this->exercice->id,
            'type' => 'impact',
        ]);
    }

    public function test_endpoint_refuse_sans_permission(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true]))
            ->post(route('suivi-evaluation.generer-impact'), ['exercice' => $this->exercice->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('evaluations_periodiques', ['type' => 'impact']);
    }
}
