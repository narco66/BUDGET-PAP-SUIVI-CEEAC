<?php

namespace Tests\Feature;

use App\Models\CategorieRecette;
use App\Models\Contribution;
use App\Models\Exercice;
use App\Models\PrevisionRecette;
use App\Models\Recette;
use App\Services\Recettes\GestionRecettesService;
use Database\Seeders\EtatMembreSeeder;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\GestionRecettesSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RecetteSeeder;
use Database\Seeders\SourceFinancementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase R1 — schéma recettes, catégories, prévisions et reprise contributions. */
class GestionRecettesPhaseR1Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            ExerciceSeeder::class,
            SourceFinancementSeeder::class,
            NomenclatureBudgetaireSeeder::class,
            EtatMembreSeeder::class,
            RecetteSeeder::class,
        ]);
        $this->exercice = Exercice::firstWhere('annee', 2026);
    }

    public function test_tables_r1_existent(): void
    {
        $this->assertTrue(Schema::hasTable('categories_recettes'));
        $this->assertTrue(Schema::hasTable('previsions_recettes'));
        $this->assertTrue(Schema::hasTable('recettes'));
    }

    public function test_seeder_cree_categories_et_reprise_2026(): void
    {
        $this->seed(GestionRecettesSeeder::class);

        $this->assertSame(5, CategorieRecette::count());
        $this->assertDatabaseHas('categories_recettes', ['code' => 'CAT-EM', 'type' => 'contribution_statutaire']);
        $this->assertDatabaseHas('categories_recettes', ['code' => 'CAT-PTF', 'type' => 'contribution_ptf']);

        $contributions = Contribution::where('exercice_id', $this->exercice->id)->count();
        $this->assertSame($contributions, PrevisionRecette::where('exercice_id', $this->exercice->id)->count());
        $this->assertSame($contributions, Recette::where('exercice_id', $this->exercice->id)->count());
    }

    public function test_reprise_lie_rubriques_etats_membres(): void
    {
        $this->seed(GestionRecettesSeeder::class);

        $prevAngola = PrevisionRecette::firstWhere('reference', 'PREV-EM-721010');
        $this->assertNotNull($prevAngola);
        $this->assertNotNull($prevAngola->rubrique_id);
        $this->assertSame('721010', $prevAngola->rubrique->code);
        $this->assertSame('CAT-EM', $prevAngola->categorie->code);
    }

    public function test_reprise_ptf_utilise_categorie_et_rubrique_729500(): void
    {
        $this->seed(GestionRecettesSeeder::class);

        $prevBad = PrevisionRecette::firstWhere('reference', 'PREV-PTF-BAD');
        $this->assertNotNull($prevBad);
        $this->assertSame('CAT-PTF', $prevBad->categorie->code);
        $this->assertSame('729500', $prevBad->rubrique->code);
    }

    public function test_synchronisation_est_idempotente(): void
    {
        $service = app(GestionRecettesService::class);
        $service->synchroniserDepuisContributions($this->exercice->id);
        $service->synchroniserDepuisContributions($this->exercice->id);

        $contributions = Contribution::where('exercice_id', $this->exercice->id)->count();
        $this->assertSame($contributions, PrevisionRecette::count());
        $this->assertSame($contributions, Recette::count());
    }

    public function test_reviser_prevision_cree_nouvelle_version(): void
    {
        $service = app(GestionRecettesService::class);
        $service->synchroniserDepuisContributions($this->exercice->id);

        $courante = PrevisionRecette::firstWhere('reference', 'PREV-EM-721010');
        $nouveauMontant = (float) $courante->montant_prevu + 1_000_000;

        $revisee = $service->reviserPrevision($courante->fresh(), $nouveauMontant, 'Révision test R1');

        $this->assertTrue($revisee->est_courante);
        $this->assertSame(2, $revisee->version);
        $this->assertEquals($nouveauMontant, (float) $revisee->montant_revise);
        $this->assertFalse($courante->fresh()->est_courante);

        $recette = Recette::where('prevision_recette_id', $revisee->id)->first();
        $this->assertNotNull($recette);
        $this->assertEquals($nouveauMontant, (float) $recette->montant_prevu);
    }

    public function test_synthese_exercice_agrege_montants(): void
    {
        $service = app(GestionRecettesService::class);
        $service->synchroniserDepuisContributions($this->exercice->id);

        $synthese = $service->syntheseExercice($this->exercice->id);

        $this->assertSame(Contribution::where('exercice_id', $this->exercice->id)->count(), $synthese['previsions']);
        $this->assertGreaterThan(0, $synthese['montant_prevu']);

        $totalAppele = (float) Contribution::where('exercice_id', $this->exercice->id)->sum('montant_appele');
        $this->assertEquals($totalAppele, $synthese['montant_prevu']);
    }
}
