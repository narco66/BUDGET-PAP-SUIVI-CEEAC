<?php

namespace Tests\Feature;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Encaissement;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Recette;
use App\Models\User;
use App\Services\Reporting\RapportComposer;
use App\Services\Tresorerie\TresorerieService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2-6 — rapports flux trésorerie et dette fournisseurs. */
class TresorerieReportingTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['mfa.enabled' => false]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);

        $titreR = BudgetTitre::create(['sens' => 'recette', 'code' => '7', 'intitule' => 'Recettes']);
        $artR = BudgetArticle::create(['titre_id' => $titreR->id, 'code' => '72', 'intitule' => 'Contrib']);
        $parR = BudgetParagraphe::create(['article_id' => $artR->id, 'code' => '721', 'intitule' => 'EM']);
        $rubR = BudgetRubrique::create(['paragraphe_id' => $parR->id, 'code' => '721010', 'intitule' => 'C']);

        $recette = Recette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'REC-RPT-1',
            'rubrique_id' => $rubR->id,
            'montant_prevu' => 5_000,
            'montant_realise' => 5_000,
            'statut' => 'realisee',
        ]);

        Encaissement::create([
            'recette_id' => $recette->id,
            'reference' => 'ENC-RPT-1',
            'montant' => 3_000,
            'mode_paiement' => 'virement',
            'date_encaissement' => '2026-04-10',
            'statut' => 'valide',
        ]);

        $engagement = Engagement::create([
            'exercice_id' => $this->exercice->id,
            'numero' => 'ENG-RPT-1',
            'objet' => 'Fourniture bureau',
            'beneficiaire' => 'SARL Fournitures CEEAC',
            'montant_total' => 2_000,
            'statut' => 'vise',
        ]);
        $liq = Liquidation::create(['numero' => 'LIQ-RPT-1', 'engagement_id' => $engagement->id, 'montant' => 2_000, 'statut' => 'valide']);
        $ord = Ordonnancement::create(['numero' => 'ORD-RPT-1', 'liquidation_id' => $liq->id, 'montant' => 2_000, 'statut' => 'valide']);

        Paiement::create([
            'numero' => 'PAY-RPT-1',
            'ordonnancement_id' => $ord->id,
            'montant' => 500,
            'date_paiement' => '2026-04-15',
            'mode_paiement' => 'virement',
            'statut' => 'valide',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_dette_par_fournisseur_agrege_reste_a_payer(): void
    {
        $dettes = app(TresorerieService::class)->detteParFournisseur($this->exercice->id);

        $this->assertCount(1, $dettes);
        $this->assertSame('SARL Fournitures CEEAC', $dettes->first()['fournisseur']);
        $this->assertEquals(1_500, $dettes->first()['reste']);
    }

    public function test_composer_flux_tresorerie_contient_journaux(): void
    {
        $doc = app(RapportComposer::class)->composer('tresorerie_flux', $this->exercice);

        $this->assertSame('tresorerie_flux', $doc['type']);
        $titres = collect($doc['sections'])->pluck('titre');
        $this->assertTrue($titres->contains('Journal des encaissements'));
        $this->assertTrue($titres->contains('Journal des décaissements (paiements)'));
    }

    public function test_composer_dette_fournisseurs_contient_synthese(): void
    {
        $doc = app(RapportComposer::class)->composer('tresorerie_dette_fournisseurs', $this->exercice);

        $this->assertSame('tresorerie_dette_fournisseurs', $doc['type']);
        $table = collect($doc['sections'])->firstWhere('titre', 'Dette par fournisseur / bénéficiaire');
        $this->assertNotNull($table);
        $this->assertStringContainsString('SARL Fournitures CEEAC', (string) json_encode($table['lignes']));
    }

    public function test_apercu_et_pdf_accessibles(): void
    {
        $user = $this->user('administrateur');

        $this->actingAs($user)
            ->get(route('reporting.show', ['type' => 'tresorerie_flux', 'exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('flux de trésorerie', false);

        $this->actingAs($user)
            ->get(route('reporting.show', ['type' => 'tresorerie_dette_fournisseurs', 'exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Dette fournisseurs', false);

        $this->actingAs($user)
            ->get(route('reporting.generer', [
                'type' => 'tresorerie_flux',
                'exercice' => $this->exercice->id,
                'format' => 'pdf',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
