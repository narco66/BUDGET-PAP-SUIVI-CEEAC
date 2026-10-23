<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Services\Pap\PapAuditService;
use App\Services\Pap\PapCompletenessService;
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
use Tests\TestCase;

/** Phase P0 — complétion données PAP (budget, indicateurs, structures, responsables). */
class PapP0Test extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_completer_p0_lie_budget_indicateurs_structures(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        $directeur = User::factory()->create(['actif' => true])->assignRole('directeur');

        $stats = app(PapCompletenessService::class)->completer($exercice->id, [
            'responsable_id' => $directeur->id,
        ]);

        $this->assertTrue($stats['succes']);
        $this->assertSame(0, $stats['anomalies_critiques']);
        $this->assertGreaterThan(0, $stats['indicateurs_crees']);
        $this->assertGreaterThan(0, $stats['liaisons_budget']);

        $activites = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exercice->id))->get();
        $this->assertGreaterThan(0, $activites->count());

        foreach ($activites as $activite) {
            $this->assertNotNull($activite->structure_id, "Structure manquante sur {$activite->code}");
            $this->assertSame($directeur->id, $activite->responsable_user_id);
            $this->assertNotNull($activite->ligne_budgetaire_id, "Budget manquant sur {$activite->code}");
            $this->assertTrue(
                Indicateur::where('rbm_type', Activite::class)->where('rbm_id', $activite->id)->exists(),
                "Indicateur manquant sur {$activite->code}"
            );
        }

        $this->assertSame(0, LigneBudgetaire::where('exercice_id', $exercice->id)->where('code', 'LB-2026-PAP1')->count());
    }

    public function test_commande_pap_completer_donnees(): void
    {
        User::factory()->create(['actif' => true])->assignRole('directeur');

        $this->artisan('pap:completer-donnees', ['--exercice' => 2026])
            ->assertSuccessful();

        $audit = app(PapAuditService::class)->executer(Exercice::firstWhere('annee', 2026)->id);
        $this->assertSame(0, $audit['anomalies_critiques']);
        $this->assertSame(0, $audit['anomalies_total']);
    }

    public function test_seeder_pap_p0(): void
    {
        User::updateOrCreate(
            ['email' => 'directeur.plan@ceeac.test'],
            ['name' => 'Dir', 'password' => bcrypt('x'), 'actif' => true]
        )->assignRole('directeur');

        $this->seed(PapP0Seeder::class);

        $exercice = Exercice::firstWhere('annee', 2026);
        $audit = app(PapAuditService::class)->executer($exercice->id);

        $this->assertSame(0, $audit['anomalies_critiques']);
        $this->assertSame(0, $audit['anomalies_total']);
    }

    public function test_completer_p0_est_idempotent(): void
    {
        $directeur = User::factory()->create(['actif' => true])->assignRole('directeur');
        $exerciceId = Exercice::firstWhere('annee', 2026)->id;
        $svc = app(PapCompletenessService::class);

        $svc->completer($exerciceId, ['responsable_id' => $directeur->id]);
        $second = $svc->completer($exerciceId, ['responsable_id' => $directeur->id]);

        $this->assertSame(0, $second['indicateurs_crees']);
        $this->assertSame(0, $second['anomalies_total']);
    }
}
