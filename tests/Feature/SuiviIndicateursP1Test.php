<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\HistoriqueMesure;
use App\Models\Indicateur;
use App\Models\Mesure;
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

/** Phase P1 — hub, tendances et rapport évolution IOV. */
class SuiviIndicateursP1Test extends TestCase
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

        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-P1', 'intitule' => 'P', 'statut' => 'actif']);
        $this->indicateur = Indicateur::create([
            'rbm_type' => Pilier::class,
            'rbm_id' => $pilier->id,
            'code' => 'IND-P1',
            'libelle' => 'Indicateur P1',
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

    public function test_hub_suivi_indicateurs(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.suivi-indicateurs.hub', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Suivi des indicateurs IOV')
            ->assertSee('IND-P1');
    }

    public function test_evolution_exercice(): void
    {
        $svc = app(MesureService::class);
        $perf = app(IndicateurPerformanceService::class);
        $user = $this->admin();

        foreach (['2026-T1' => 50, '2026-T2' => 80] as $periode => $valeur) {
            $m = $svc->enregistrer($this->indicateur, ['periode' => $periode, 'valeur_realisee' => $valeur], $user->id);
            $svc->valider($m, $user->id);
        }

        $rows = $perf->evolutionExercice($this->exercice->id);
        $row = $rows->firstWhere('code', 'IND-P1');

        $this->assertNotNull($row);
        $this->assertEquals('2026-T2', $row['derniere_periode']);
        $this->assertEquals(60.0, $row['variation_pct']);
    }

    public function test_vue_indicateur_affiche_historique(): void
    {
        $svc = app(MesureService::class);
        $user = $this->admin();
        $pilier = Pilier::firstWhere('code', 'P-P1');

        $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 40], $user->id);
        $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 55], $user->id);

        $this->assertGreaterThan(0, HistoriqueMesure::where('indicateur_id', $this->indicateur->id)->count());

        $this->actingAs($user)
            ->get(route('rbm.indicateurs.index', ['niveau' => 'pilier', 'id' => $pilier->id, 'exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Historique')
            ->assertSee('modification');
    }

    public function test_rapport_indicateurs_evolution(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reporting.show', [
                'type' => 'indicateurs_evolution',
                'exercice' => $this->exercice->id,
            ]))
            ->assertOk()
            ->assertSee('Évolution et écarts');
    }
}
