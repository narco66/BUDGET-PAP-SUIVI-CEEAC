<?php

namespace Tests\Feature;

use App\Models\AuditIntegriteData;
use App\Models\Exercice;
use App\Models\Structure;
use App\Models\User;
use App\Services\Audit\DataIntegrityCheckService;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2-10 — contrôle d'intégrité global des données. */
class DataIntegrityCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
    }

    public function test_commande_integrite_s_execute_en_mode_transversal(): void
    {
        $this->artisan('data:integrity-check', ['--module' => ['transversal']])
            ->assertSuccessful();
    }

    public function test_detecte_utilisateur_orphelin(): void
    {
        $structure = Structure::create(['code' => 'TMP', 'libelle' => 'Temporaire', 'type' => 'departement']);
        User::factory()->create(['actif' => true, 'statut' => 'actif', 'structure_id' => $structure->id]);
        $structure->delete();

        $rapport = app(DataIntegrityCheckService::class)->executer(
            Exercice::first()->id,
            ['transversal'],
        );

        $this->assertTrue(
            collect($rapport['anomalies'])->contains(fn (array $a) => $a['code'] === 'USER_ORPHELIN')
        );
    }

    public function test_persister_enregistre_un_snapshot(): void
    {
        $exercice = Exercice::first();

        $this->artisan('data:integrity-check', [
            '--module' => ['transversal'],
            '--persister' => true,
            '--exercice' => $exercice->id,
        ])->assertSuccessful();

        $this->assertDatabaseHas('audit_integrite_data', [
            'exercice_id' => $exercice->id,
        ]);
        $this->assertNotNull(AuditIntegriteData::first()?->snapshot);
    }

    public function test_json_et_seuil_critique(): void
    {
        $structure = Structure::create(['code' => 'TST', 'libelle' => 'Test', 'type' => 'departement']);
        User::factory()->create(['actif' => true, 'statut' => 'actif', 'structure_id' => $structure->id]);
        $structure->delete();

        $service = app(DataIntegrityCheckService::class);
        $rapport = $service->executer(Exercice::first()->id, ['transversal']);

        $this->assertFalse($service->depasseSeuil($rapport['anomalies'], 'critique'));
        $this->assertTrue($service->depasseSeuil($rapport['anomalies'], 'warning'));
    }

    public function test_desactive_ne_lance_pas_les_controles(): void
    {
        config(['audit.integrity.enabled' => false]);

        $this->artisan('data:integrity-check')
            ->assertSuccessful();

        $this->assertSame(0, AuditIntegriteData::count());
    }
}
