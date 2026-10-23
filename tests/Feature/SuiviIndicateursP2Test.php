<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\PerformanceIndicateur;
use App\Models\Pilier;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Models\User;
use App\Services\Rbm\IndicateurPerformanceService;
use App\Services\Rbm\MesureService;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P2 — snapshots performances_indicateurs. */
class SuiviIndicateursP2Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private Indicateur $indicateur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmReferentielSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);

        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-P2', 'intitule' => 'P', 'statut' => 'actif']);
        $this->indicateur = Indicateur::create([
            'rbm_type' => Pilier::class,
            'rbm_id' => $pilier->id,
            'code' => 'IND-P2',
            'libelle' => 'Indicateur P2',
            'type' => 'operationnel',
            'type_indicateur_id' => TypeIndicateur::where('code', 'OPE')->value('id'),
            'unite_mesure_id' => UniteMesure::where('code', 'PCT')->value('id'),
            'periodicite' => 'trimestriel',
            'valeur_cible' => 100,
            'seuil_alerte' => 50,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_capture_performance_a_la_validation(): void
    {
        $svc = app(MesureService::class);
        $user = $this->admin();
        $mesure = $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 85], $user->id);
        $svc->valider($mesure, $user->id);

        $this->assertDatabaseHas('performances_indicateurs', [
            'indicateur_id' => $this->indicateur->id,
            'periode' => '2026-T1',
            'exercice_id' => $this->exercice->id,
        ]);

        $perf = PerformanceIndicateur::first();
        $this->assertEquals(85, (float) $perf->valeur_realisee);
        $this->assertEquals('vert', $perf->feu);
    }

    public function test_commande_capture_exercice(): void
    {
        $svc = app(MesureService::class);
        $user = $this->admin();
        $m = $svc->enregistrer($this->indicateur, ['periode' => '2026-T2', 'valeur_realisee' => 60], $user->id);
        $svc->valider($m, $user->id);

        $n = app(IndicateurPerformanceService::class)->capturerExercice($this->exercice->id, $user->id);

        $this->assertGreaterThanOrEqual(1, $n);
        $this->assertEquals(1, PerformanceIndicateur::where('exercice_id', $this->exercice->id)->count());
    }

    public function test_hub_affiche_snapshots(): void
    {
        $svc = app(MesureService::class);
        $user = $this->admin();
        $m = $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 70], $user->id);
        $svc->valider($m, $user->id);

        $this->actingAs($user)
            ->get(route('rbm.suivi-indicateurs.hub', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Snapshots IOV');
    }
}
