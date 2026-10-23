<?php

namespace Tests\Feature;

use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\User;
use App\Imports\LignesBudgetairesImport;
use App\Services\Budget\BudgetAuditService;
use App\Services\Budget\NomenclatureResolver;
use App\Services\Budget\RbmBudgetSyncService;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\PartenaireFinancierSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SourceFinancementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ControlesBudgetPhaseCTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(SourceFinancementSeeder::class);
        $this->seed(PartenaireFinancierSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);
        $this->admin = User::create([
            'name' => 'Admin Phase C', 'email' => 'admin.phasec@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_resolver_rubrique_avec_paragraphe(): void
    {
        $rubrique = BudgetRubrique::with('paragraphe')->first();
        $this->assertNotNull($rubrique);

        $resolver = app(NomenclatureResolver::class);
        $trouvee = $resolver->resoudreRubrique([
            'rubrique_code' => $rubrique->code,
            'paragraphe_code' => $rubrique->paragraphe->code,
        ]);

        $this->assertNotNull($trouvee);
        $this->assertSame($rubrique->id, $trouvee->id);
    }

    public function test_import_lignes_rejette_rubrique_ambigue(): void
    {
        $paragrapheA = BudgetParagraphe::first();
        $paragrapheB = BudgetParagraphe::whereKeyNot($paragrapheA->id)->first();
        $this->assertNotNull($paragrapheB);

        BudgetRubrique::create(['paragraphe_id' => $paragrapheA->id, 'code' => 'DUP-TEST', 'intitule' => 'A']);
        BudgetRubrique::create(['paragraphe_id' => $paragrapheB->id, 'code' => 'DUP-TEST', 'intitule' => 'B']);

        $import = new LignesBudgetairesImport(Exercice::firstWhere('annee', 2026)->id);
        $import->collection(new Collection([
            collect(['code' => 'LB-DUP', 'rubrique_code' => 'DUP-TEST', 'montant_ouvert' => 1000]),
        ]));

        $this->assertSame(0, $import->importees);
        $this->assertNotEmpty($import->rejets);
    }

    public function test_middleware_bloque_budget_sur_exercice_clos(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        $exercice->update(['statut' => 'clos', 'date_cloture' => now(), 'cloture_par' => $this->admin->id]);
        $rubrique = BudgetRubrique::first();

        $this->actingAs($this->admin)->post(route('budget.store'), [
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-CLOS',
            'montant_ouvert' => 1000,
        ])->assertRedirect()->assertSessionHas('erreur');

        $this->assertDatabaseMissing('lignes_budgetaires', ['code' => 'LB-CLOS']);
    }

    public function test_audit_enrichi_contient_sections_phase_c(): void
    {
        $rapport = app(BudgetAuditService::class)->executer(Exercice::firstWhere('annee', 2026)->id);

        $this->assertArrayHasKey('exercices', $rapport);
        $this->assertArrayHasKey('referentiel_financier', $rapport);
        $this->assertArrayHasKey('ptf', $rapport);
        $this->assertArrayHasKey('anomalies_total', $rapport);
    }

    public function test_propagation_ceeac_ptf_activite_vers_ligne(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        $rubrique = BudgetRubrique::first();
        $ceeac = SourceFinancement::firstWhere('code', 'CEEAC-EM');
        $bad = SourceFinancement::firstWhere('code', 'BAD');

        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rubrique->id,
            'source_financement_id' => $bad->id,
            'code' => 'LB-VENTIL',
            'montant_ouvert' => 1000000,
        ]);

        $pilier = \App\Models\Pilier::create(['exercice_id' => $exercice->id, 'code' => 'PC', 'intitule' => 'Pilier C', 'statut' => 'actif']);
        $axe = $pilier->axes()->create(['code' => 'AC', 'intitule' => 'Axe C', 'statut' => 'actif']);
        $produit = $axe->produits()->create(['code' => 'PRC', 'intitule' => 'Produit C', 'statut' => 'actif']);
        $sp = $produit->sousProduits()->create(['code' => 'SPC', 'intitule' => 'SP C', 'statut' => 'actif']);
        $activite = $sp->activites()->create([
            'code' => 'ACT-VENTIL',
            'intitule' => 'Activité test ventilation',
            'montant_total' => 1000000,
            'montant_ceeac' => 600000,
            'montant_ptf' => 400000,
            'ligne_budgetaire_id' => $ligne->id,
            'statut' => 'actif',
        ]);

        $ligne->update(['activite_id' => $activite->id]);
        app(RbmBudgetSyncService::class)->synchroniserLigne($ligne->fresh());

        $this->assertSame(2, $ligne->fresh()->sourcesFinancement()->count());
        $this->assertEqualsWithDelta(600000, (float) $ligne->sourcesFinancement()->where('source_financement_id', $ceeac->id)->value('montant'), 0.01);
        $this->assertEqualsWithDelta(400000, (float) $ligne->sourcesFinancement()->where('source_financement_id', $bad->id)->value('montant'), 0.01);
    }
}
