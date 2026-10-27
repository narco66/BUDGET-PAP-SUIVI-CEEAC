<?php

namespace Tests\Feature;

use App\Imports\LignesBudgetairesImport;
use App\Imports\NomenclatureImport;
use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function csv(string $contenu): string
    {
        $path = storage_path('app/test_'.uniqid().'.csv');
        file_put_contents($path, $contenu);

        return $path;
    }

    public function test_import_nomenclature_cree_la_hierarchie(): void
    {
        $path = $this->csv(
            "sens,titre_code,titre_intitule,article_code,article_intitule,paragraphe_code,paragraphe_intitule,rubrique_code,rubrique_intitule\n".
            "depense,1,Personnel,66,Charges,661,Traitements,661000,Salaires de base\n"
        );

        Excel::import(new NomenclatureImport, $path);
        @unlink($path);

        $this->assertDatabaseHas('budget_titres', ['sens' => 'depense', 'code' => '1']);
        $this->assertDatabaseHas('budget_articles', ['code' => '66']);
        $this->assertDatabaseHas('budget_paragraphes', ['code' => '661']);
        $this->assertDatabaseHas('budget_rubriques', ['code' => '661000', 'intitule' => 'Salaires de base']);
    }

    public function test_import_lignes_resout_les_fk_et_montant(): void
    {
        // nomenclature minimale
        $titre = BudgetTitre::create(['sens' => 'depense', 'code' => '1', 'intitule' => 'Personnel']);
        $article = BudgetArticle::create(['titre_id' => $titre->id, 'code' => '66', 'intitule' => 'Charges']);
        $para = BudgetParagraphe::create(['article_id' => $article->id, 'code' => '661', 'intitule' => 'T']);
        BudgetRubrique::create(['paragraphe_id' => $para->id, 'code' => '661000', 'intitule' => 'Salaires']);

        $structure = Structure::updateOrCreate(
            ['code' => 'SG'],
            ['libelle' => 'Secrétariat Général', 'type' => 'secretariat_general']
        );
        SourceFinancement::create(['code' => 'CEEAC-EM', 'libelle' => 'Interne', 'type' => 'interne_ceeac']);

        $path = $this->csv(
            "code,intitule,structure_code,rubrique_code,source_code,montant_ouvert\n".
            "LB-1,Salaires,SG,661000,CEEAC-EM,1000000\n".
            "LB-2,Inconnue,SG,999999,CEEAC-EM,500000\n" // rubrique introuvable -> rejet
        );

        $import = new LignesBudgetairesImport($this->exercice->id);
        Excel::import($import, $path);
        @unlink($path);

        $this->assertSame(1, $import->importees);
        $this->assertCount(1, $import->rejets);

        $ligne = LigneBudgetaire::firstWhere('code', 'LB-1');
        $this->assertEquals($structure->id, $ligne->structure_id);
        $this->assertEquals(1000000, (float) $ligne->montant_ouvert);
    }

    public function test_creation_ligne_via_http(): void
    {
        $rubrique = $this->rubrique();

        $this->actingAs($this->user('administrateur'))
            ->post(route('budget.store'), [
                'exercice_id' => $this->exercice->id,
                'rubrique_id' => $rubrique->id,
                'code' => 'LB-100',
                'intitule' => 'Test',
                'montant_ouvert' => '2 500 000',
            ])->assertRedirect();

        $this->assertDatabaseHas('lignes_budgetaires', ['code' => 'LB-100', 'montant_ouvert' => 2500000]);
    }

    public function test_modification_ligne_ignore_les_lignes_de_ventilation_vides(): void
    {
        config(['mfa.enabled' => false]);
        $source = SourceFinancement::create(['code' => 'CEEAC-EM', 'libelle' => 'Interne', 'type' => 'interne_ceeac']);
        $ligne = LigneBudgetaire::create(['exercice_id' => $this->exercice->id, 'rubrique_id' => $this->rubrique()->id, 'code' => 'ACT-2011', 'montant_ouvert' => 655000000]);
        $donnees = [
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $ligne->rubrique_id,
            'code' => 'ACT-2011',
            'montant_ouvert' => '655 000 000',
            'source_principale_idx' => '0',
            'sources' => [
                ['source_financement_id' => $source->id, 'montant' => '655000000', 'est_principale' => '1'],
                ['source_financement_id' => '', 'montant' => '', 'est_principale' => '0'],
                ['source_financement_id' => '', 'montant' => '0', 'est_principale' => '0'],
            ],
        ];

        $this->actingAs($this->user('administrateur'))
            ->put(route('budget.update', $ligne), $donnees)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ligne_source_financement', ['ligne_budgetaire_id' => $ligne->id, 'source_financement_id' => $source->id]);

        // Ligne incomplète (montant sans source) : message traduit, pas la clé brute.
        $donnees['sources'][1] = ['source_financement_id' => '', 'montant' => '1000', 'est_principale' => '0'];
        $this->actingAs($this->user('administrateur'))
            ->put(route('budget.update', $ligne), $donnees)
            ->assertSessionHasErrors(['sources.1.source_financement_id' => 'Le champ source de financement (ventilation) est obligatoire quand sources est renseigné.']);
    }

    public function test_controle_des_credits_disponible(): void
    {
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $this->rubrique()->id,
            'code' => 'LB-200',
            'montant_ouvert' => 1000,
            'montant_engage' => 300,
        ]);

        $this->assertEquals(700, $ligne->disponible);
        $this->assertTrue($ligne->peutEngager(700));
        $this->assertFalse($ligne->peutEngager(800));
        $this->assertEquals(30.0, $ligne->taux_consommation);
    }

    public function test_consultation_budget_accessible(): void
    {
        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->get(route('budget.index', ['exercice' => $this->exercice->id]))
            ->assertOk();
    }

    public function test_export_refuse_sans_droit(): void
    {
        // point_focal n'a pas ligne_budgetaire.exporter
        $this->actingAs($this->user('point_focal'))
            ->get(route('budget.export', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }

    private function rubrique(): BudgetRubrique
    {
        $titre = BudgetTitre::create(['sens' => 'depense', 'code' => '2', 'intitule' => 'Fonct']);
        $article = BudgetArticle::create(['titre_id' => $titre->id, 'code' => '60', 'intitule' => 'Achats']);
        $para = BudgetParagraphe::create(['article_id' => $article->id, 'code' => '601', 'intitule' => 'Fourn']);

        return BudgetRubrique::create(['paragraphe_id' => $para->id, 'code' => '601100', 'intitule' => 'Bureau']);
    }
}
