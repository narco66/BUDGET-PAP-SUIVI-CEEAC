<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\HistoriqueMesure;
use App\Models\Indicateur;
use App\Models\JournalAudit;
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

/** Phase P0 — historisation mesures IOV et calculs KPI unifiés. */
class SuiviIndicateursP0Test extends TestCase
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

        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-P0', 'intitule' => 'P', 'statut' => 'actif']);
        $this->indicateur = Indicateur::create([
            'rbm_type' => Pilier::class,
            'rbm_id' => $pilier->id,
            'code' => 'IND-P0',
            'libelle' => 'Indicateur P0',
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

    public function test_historise_modification_mesure(): void
    {
        $svc = app(MesureService::class);
        $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 80], $this->admin()->id);
        $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 90], $this->admin()->id);

        $this->assertEquals(90, (float) Mesure::first()->valeur_realisee);
        $this->assertDatabaseHas('historique_mesures', [
            'indicateur_id' => $this->indicateur->id,
            'periode' => '2026-T1',
            'action' => 'modification',
            'valeur_avant' => 80,
            'valeur_apres' => 90,
        ]);
    }

    public function test_refuse_modification_mesure_validee(): void
    {
        $svc = app(MesureService::class);
        $user = $this->admin();
        $mesure = $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 80], $user->id);
        $svc->valider($mesure, $user->id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 99], $user->id);
    }

    public function test_historise_validation(): void
    {
        $svc = app(MesureService::class);
        $user = $this->admin();
        $mesure = $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 75], $user->id);
        $svc->verifier($mesure, $user->id);
        $svc->valider($mesure->fresh(), $user->id);

        $this->assertDatabaseHas('historique_mesures', ['action' => 'verification']);
        $this->assertDatabaseHas('historique_mesures', ['action' => 'validation']);
        $this->assertNotNull($mesure->fresh()->valide_le);
    }

    public function test_auditable_mesure(): void
    {
        $svc = app(MesureService::class);
        $mesure = $svc->enregistrer($this->indicateur, ['periode' => '2026-T1', 'valeur_realisee' => 60], $this->admin()->id);

        $this->assertGreaterThan(0, JournalAudit::where('auditable_type', Mesure::class)->where('auditable_id', $mesure->id)->count());
    }

    public function test_tendance_periodes(): void
    {
        $svc = app(MesureService::class);
        $perf = app(IndicateurPerformanceService::class);
        $user = $this->admin();

        foreach (['2026-T1' => 50, '2026-T2' => 75] as $periode => $valeur) {
            $m = $svc->enregistrer($this->indicateur, ['periode' => $periode, 'valeur_realisee' => $valeur], $user->id);
            $svc->valider($m, $user->id);
        }

        $tendance = $perf->tendance($this->indicateur, '2026-T2');

        $this->assertNotNull($tendance);
        $this->assertEquals(25, $tendance['variation_abs']);
    }

    public function test_calcul_unifie_mesure_et_performance(): void
    {
        $svc = app(MesureService::class);
        $perf = app(IndicateurPerformanceService::class);

        $a = $svc->calculerTauxEtFeu($this->indicateur, 80);
        $b = $perf->calculerTauxEtFeu($this->indicateur, 80);

        $this->assertEquals($a, $b);
        $this->assertEquals('vert', $a['feu']);
    }
}
