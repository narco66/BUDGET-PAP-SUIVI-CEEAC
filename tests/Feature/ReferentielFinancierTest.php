<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\PartenaireFinancier;
use App\Models\SourceFinancement;
use App\Models\User;
use App\Services\Budget\LigneFinancementService;
use App\Services\Reporting\ReportingService;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\PartenaireFinancierSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SourceFinancementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferentielFinancierTest extends TestCase
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
            'name' => 'Admin Ref', 'email' => 'admin.ref@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_crud_source_financement(): void
    {
        $this->actingAs($this->admin)->post(route('sources.store'), [
            'code' => 'FONDS-SPEC', 'libelle' => 'Fonds spécial test', 'type' => 'interne_ceeac',
            'classification' => 'fonds_special', 'actif' => 1,
        ])->assertRedirect();

        $source = SourceFinancement::firstWhere('code', 'FONDS-SPEC');
        $this->assertNotNull($source);

        $this->actingAs($this->admin)->get(route('sources.index'))
            ->assertOk()->assertSee('Fonds spécial test');
    }

    public function test_crud_partenaire_et_convention(): void
    {
        $this->actingAs($this->admin)->post(route('partenaires.store'), [
            'code' => 'UNESCO', 'libelle' => 'UNESCO', 'sigle' => 'UNESCO', 'type' => 'onu', 'actif' => 1,
        ])->assertRedirect();

        $ptf = PartenaireFinancier::firstWhere('code', 'UNESCO');
        $source = SourceFinancement::firstWhere('code', 'BAD');

        $this->actingAs($this->admin)->post(route('conventions.store'), [
            'partenaire_id' => $ptf->id,
            'source_financement_id' => $source->id,
            'exercice_id' => Exercice::firstWhere('annee', 2026)->id,
            'code' => 'CONV-BAD-2026',
            'libelle' => 'Convention BAD 2026',
            'montant_accord' => 500000000,
            'statut' => 'actif',
            'actif' => 1,
        ])->assertRedirect();

        $this->actingAs($this->admin)->get(route('partenaires.show', $ptf))
            ->assertOk()->assertSee('Convention BAD 2026');
    }

    public function test_financement_multi_sources_ligne(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        $rubrique = \App\Models\BudgetRubrique::first();
        $ceeac = SourceFinancement::firstWhere('code', 'CEEAC-EM');
        $bad = SourceFinancement::firstWhere('code', 'BAD');

        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'TEST-MULTI',
            'intitule' => 'Ligne multi-sources',
            'montant_ouvert' => 1000000,
        ]);

        app(LigneFinancementService::class)->synchroniser($ligne, [
            ['source_financement_id' => $ceeac->id, 'montant' => 600000, 'est_principale' => true],
            ['source_financement_id' => $bad->id, 'montant' => 400000, 'est_principale' => false],
        ]);

        $this->assertSame(2, $ligne->sourcesFinancement()->count());
        $this->assertSame($ceeac->id, $ligne->fresh()->source_financement_id);

        $global = app(ReportingService::class)->global($exercice->id);
        $bailleur = app(ReportingService::class)->parBailleur($exercice->id);
        $this->assertEqualsWithDelta($global['ouvert'], $bailleur['sources']->sum('ouvert'), 0.01);
    }

    public function test_partenaires_lies_aux_sources_seeder(): void
    {
        $bad = SourceFinancement::with('partenaire')->firstWhere('code', 'BAD');
        $this->assertNotNull($bad->partenaire);
        $this->assertSame('BAD', $bad->partenaire->code);
    }
}
