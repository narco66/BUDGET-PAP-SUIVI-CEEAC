<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Pap;
use App\Models\User;
use Database\Seeders\Budget2026Seeder;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\Pap2026Seeder;
use Database\Seeders\PapP0Seeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SourceFinancementSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase P5 — qualité : permissions alias, exports, index, policies. */
class PapP5Test extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    private User $lecteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            StructureSeeder::class,
            ExerciceSeeder::class,
            SourceFinancementSeeder::class,
            NomenclatureBudgetaireSeeder::class,
            Pap2026Seeder::class,
            Budget2026Seeder::class,
            WorkflowSeeder::class,
        ]);
        $this->directeur = User::factory()->create(['actif' => true])->assignRole('directeur');
        $this->lecteur = User::factory()->create(['actif' => true])->assignRole('lecteur');
        $this->seed(PapP0Seeder::class);
    }

    public function test_alias_permission_pap_view(): void
    {
        $this->assertTrue($this->directeur->can('pap.view'));
        $this->assertTrue($this->directeur->can('pap.create'));
        $this->assertTrue($this->directeur->can('pap.update'));
        $this->assertFalse($this->lecteur->can('pap.create'));
    }

    public function test_index_performance_pap_historiques(): void
    {
        $this->assertTrue(Schema::hasTable('pap_historiques'));
        $indexes = collect(Schema::getConnection()->select("PRAGMA index_list('pap_historiques')"))
            ->pluck('name');
        $this->assertTrue($indexes->contains('pap_historiques_pap_version_idx'));
    }

    public function test_export_liste_pap_excel(): void
    {
        $this->actingAs($this->directeur)
            ->get(route('paps.export.liste'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_liste_refuse_sans_droit(): void
    {
        $this->actingAs($this->lecteur)
            ->get(route('paps.export.liste'))
            ->assertForbidden();
    }

    public function test_rapport_pap_multi_format_via_reporting(): void
    {
        Storage::fake('local');
        $exercice = Exercice::firstWhere('annee', 2026);
        $admin = User::factory()->create(['actif' => true])->assignRole('administrateur');

        $formats = [
            'pdf' => 'application/pdf',
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'word' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'csv' => 'text/csv',
        ];

        foreach ($formats as $format => $mime) {
            $response = $this->actingAs($admin)->get(route('reporting.generer', [
                'type' => 'pap',
                'format' => $format,
                'exercice' => $exercice->id,
            ]));
            $response->assertOk();
            $this->assertStringContainsString(
                explode('/', $mime)[0],
                (string) $response->headers->get('content-type'),
            );
        }
    }

    public function test_suppression_interdite_si_piliers_lies(): void
    {
        $pap = Pap::firstWhere('exercice_id', Exercice::firstWhere('annee', 2026)->id);
        $this->assertNotNull($pap);
        $this->assertSame('brouillon', $pap->statut);
        $this->assertTrue($pap->piliers()->exists());

        $this->actingAs($this->directeur)
            ->delete(route('paps.destroy', $pap))
            ->assertForbidden();
    }

    public function test_liste_pap_bouton_export_visible(): void
    {
        $this->actingAs($this->directeur)
            ->get(route('paps.index'))
            ->assertOk()
            ->assertSee('Exporter');
    }

    public function test_acces_pap_via_alias_view_policy(): void
    {
        $pap = Pap::first();

        $this->actingAs($this->directeur)
            ->get(route('paps.show', $pap))
            ->assertOk();

        $userViewOnly = User::factory()->create(['actif' => true]);
        $userViewOnly->givePermissionTo('pap.voir');

        $this->actingAs($userViewOnly)
            ->get(route('paps.show', $pap))
            ->assertOk();
    }
}
