<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\SourceFinancement;
use App\Services\Budget\BudgetAuditService;
use App\Services\Budget\BudgetCompletenessService;
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

/** Phase B0 — complétion données budget (rapprochements, ventilations PTF). */
class BudgetB0Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

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
        $this->exercice = Exercice::firstWhere('annee', 2026);
    }

    public function test_corrige_ventilation_proportionnelle(): void
    {
        $this->seed(PapP0Seeder::class);

        $activite = $this->creerActivite('ACT-B0-01', 2_000_000_000, 1_000_000_000, 3_000_000_000);
        $ligne = $this->creerLigne('ACT-B0-01', 3_000_000_000);
        $activite->update(['ligne_budgetaire_id' => $ligne->id]);
        $ligne->update(['activite_id' => $activite->id]);

        $stats = app(BudgetCompletenessService::class)->completer($this->exercice->id, [
            'rapprocher' => false,
        ]);

        $ligne->refresh()->load('sourcesFinancement');
        $somme = (float) $ligne->sourcesFinancement->sum('montant');

        $this->assertEqualsWithDelta(3_000_000_000, $somme, 0.05);
        $this->assertSame(0, $stats['anomalies_total']);
        $this->assertTrue($stats['succes']);
    }

    public function test_commande_budget_completer_donnees(): void
    {
        $this->seed(PapP0Seeder::class);

        $this->artisan('budget:completer-donnees', ['--exercice' => 2026])
            ->assertSuccessful();

        $audit = app(BudgetAuditService::class)->executer($this->exercice->id);
        $this->assertSame(0, $audit['anomalies_total']);
    }

    public function test_seeder_budget_b0(): void
    {
        $this->seed(PapP0Seeder::class);
        $this->seed(\Database\Seeders\BudgetB0Seeder::class);

        $audit = app(BudgetAuditService::class)->executer($this->exercice->id);
        $this->assertSame(0, $audit['anomalies_total']);
    }

    private function creerActivite(string $code, float $ceeac, float $ptf, float $total): Activite
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'Pilier']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'SP']);

        return Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $produit->id,
            'code' => $code,
            'intitule' => "Activité {$code}",
            'montant_ceeac' => $ceeac,
            'montant_ptf' => $ptf,
            'montant_total' => $total,
        ]);
    }

    private function creerLigne(string $code, float $montant): LigneBudgetaire
    {
        $source = SourceFinancement::firstWhere('code', 'CEEAC-EM');

        return LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => BudgetRubrique::first()->id,
            'source_financement_id' => $source?->id,
            'code' => $code,
            'intitule' => "Ligne {$code}",
            'montant_ouvert' => $montant,
        ]);
    }
}
