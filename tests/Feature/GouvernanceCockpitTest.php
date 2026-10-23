<?php

namespace Tests\Feature;

use App\Models\Risque;
use App\Models\User;
use App\Services\Gouvernance\GouvernanceService;
use Database\Seeders\AuditControleConformiteSeeder;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot D — cockpit de gouvernance (agrégation des services d'audit + risques + piste d'audit) + export risques. */
class GouvernanceCockpitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AuditControleConformiteSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Gov', 'email' => 'admin.gov@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_service_agrege_la_conformite_par_domaine(): void
    {
        $exercice = \App\Models\Exercice::firstWhere('annee', 2026) ?? \App\Models\Exercice::first();
        $conformite = app(GouvernanceService::class)->conformiteParDomaine($exercice->id);

        $this->assertCount(5, $conformite);
        foreach ($conformite as $d) {
            $this->assertArrayHasKey('domaine', $d);
            $this->assertArrayHasKey('anomalies', $d);
        }
        // Le score global est un entier 0-100.
        $score = app(GouvernanceService::class)->scoreGlobal($conformite);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_cockpit_accessible_et_affiche_les_sections(): void
    {
        Risque::create(['code' => 'R-G1', 'libelle' => 'Risque critique', 'probabilite' => 4, 'impact' => 4, 'statut' => 'identifie']);

        $this->actingAs($this->admin)->get(route('gouvernance.cockpit'))
            ->assertOk()
            ->assertSee('Cockpit gouvernance', false)
            ->assertSee('Conformité par domaine', false)
            ->assertSee('Registre des risques', false)
            ->assertSee("Piste d'audit", false);
    }

    public function test_cockpit_refuse_sans_droit_audit(): void
    {
        $sansDroit = User::create([
            'name' => 'No Audit', 'email' => 'noaudit.gov@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);

        $this->actingAs($sansDroit)->get(route('gouvernance.cockpit'))->assertForbidden();
    }

    public function test_export_registre_risques(): void
    {
        Risque::create(['code' => 'R-EXP', 'libelle' => 'Risque export', 'probabilite' => 3, 'impact' => 3, 'statut' => 'identifie']);

        $pdf = $this->actingAs($this->admin)->get(route('risques.export', 'pdf'))->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));

        $xlsx = $this->actingAs($this->admin)->get(route('risques.export', 'xlsx'))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type'));
    }
}
