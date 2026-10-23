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
use App\Models\User;
use App\Services\Budget\PapBudgetRapprochementService;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rapprochement PAP ↔ lignes budgétaires (consolidation point 4). */
class PapBudgetRapprochementTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);
        $this->exercice = Exercice::firstWhere('annee', 2026);
        $this->admin = User::create([
            'name' => 'Admin Rapp', 'email' => 'admin.rapp@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_detecte_liaison_possible_par_code(): void
    {
        $activite = $this->creerActivite('ACT-001', 100_000_000);
        $this->creerLigne('ACT-001', 100_000_000);

        $analyse = app(PapBudgetRapprochementService::class)->analyser($this->exercice->id);

        $this->assertSame(1, $analyse['ecarts_total']);
        $this->assertSame('LIAISON_POSSIBLE', $analyse['ecarts'][0]['type']);
        $this->assertNull($activite->fresh()->ligne_budgetaire_id);
    }

    public function test_rapprochement_lie_par_code(): void
    {
        $activite = $this->creerActivite('ACT-002', 50_000_000);
        $ligne = $this->creerLigne('ACT-002', 50_000_000);

        $stats = app(PapBudgetRapprochementService::class)->rapprocher($this->exercice->id);

        $this->assertSame(1, $stats['liaisons_creees']);
        $this->assertSame($ligne->id, $activite->fresh()->ligne_budgetaire_id);
        $this->assertSame($activite->id, $ligne->fresh()->activite_id);

        $apres = app(PapBudgetRapprochementService::class)->analyser($this->exercice->id);
        $this->assertSame(0, $apres['ecarts_total']);
    }

    public function test_aligne_montant_ouvert_sur_pap(): void
    {
        $activite = $this->creerActivite('ACT-003', 80_000_000);
        $ligne = $this->creerLigne('ACT-003', 60_000_000);
        $activite->update(['ligne_budgetaire_id' => $ligne->id]);
        $ligne->update(['activite_id' => $activite->id]);

        $stats = app(PapBudgetRapprochementService::class)->rapprocher($this->exercice->id, [
            'aligner_montants' => true,
        ]);

        $this->assertSame(1, $stats['montants_alignes']);
        $this->assertEquals(80_000_000, (float) $ligne->fresh()->montant_ouvert);
    }

    public function test_commande_rapprocher_pap(): void
    {
        $this->creerActivite('ACT-CMD', 25_000_000);
        $this->creerLigne('ACT-CMD', 25_000_000);

        $this->artisan('budget:rapprocher-pap', ['--exercice' => 2026, '--dry-run' => true])
            ->assertSuccessful();

        $this->artisan('budget:rapprocher-pap', ['--exercice' => 2026])
            ->assertSuccessful();
    }

    public function test_ui_rapprochement(): void
    {
        $this->creerActivite('ACT-UI', 10_000_000);
        $this->creerLigne('ACT-UI', 10_000_000);

        $this->actingAs($this->admin)
            ->get(route('budget.import.form', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Rapprochement PAP');

        $this->actingAs($this->admin)
            ->post(route('budget.rapprochement.executer'), ['exercice_id' => $this->exercice->id])
            ->assertRedirect(route('budget.import.form', ['exercice' => $this->exercice->id]));
    }

    private function creerActivite(string $code, float $montant): Activite
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
            'montant_ceeac' => $montant,
            'montant_ptf' => 0,
            'montant_total' => $montant,
        ]);
    }

    private function creerLigne(string $code, float $montant): LigneBudgetaire
    {
        return LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => BudgetRubrique::first()->id,
            'code' => $code,
            'intitule' => "Ligne {$code}",
            'montant_ouvert' => $montant,
        ]);
    }
}
