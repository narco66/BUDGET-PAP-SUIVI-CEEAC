<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\LigneBudgetaire;
use App\Models\Mesure;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Services\Pap\PapPerformanceService;
use App\Services\Reporting\RapportComposer;
use App\Services\Reporting\ReportingService;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase E5 — enrichissement rapport rbm_gar (multi-IOV, taux financier réel). */
class RbmPhaseE5Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private Activite $activite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);
        $this->seed(RbmReferentielSeeder::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $this->activite = Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $prod->id,
            'code' => 'ACT1',
            'intitule' => 'Activité test',
            'montant_total' => 1000,
            'taux_realisation_physique' => 60,
        ]);

        $rubrique = \App\Models\BudgetRubrique::first();
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-E5',
            'montant_ouvert' => 1000,
            'montant_engage' => 400,
            'montant_paye' => 250,
            'activite_id' => $this->activite->id,
        ]);
        $this->activite->update(['ligne_budgetaire_id' => $ligne->id]);
    }

    private function creerIndicateur(string $code, string $libelle): Indicateur
    {
        return Indicateur::create([
            'rbm_type' => Activite::class,
            'rbm_id' => $this->activite->id,
            'code' => $code,
            'libelle' => $libelle,
            'type' => 'operationnel',
            'type_indicateur_id' => TypeIndicateur::where('code', 'OPE')->value('id'),
            'unite_mesure_id' => UniteMesure::where('code', 'PCT')->value('id'),
            'unite' => '%',
            'periodicite' => 'trimestriel',
            'valeur_cible' => 100,
        ]);
    }

    public function test_rbm_gar_liste_tous_indicateurs(): void
    {
        $ind1 = $this->creerIndicateur('IND-A', 'IOV A');
        $ind2 = $this->creerIndicateur('IND-B', 'IOV B');
        Mesure::create([
            'indicateur_id' => $ind1->id,
            'periode' => '2026-T1',
            'valeur_realisee' => 80,
            'taux_atteinte' => 80,
            'feu' => 'vert',
            'statut' => 'validee',
        ]);

        $rows = app(ReportingService::class)->rbmGar($this->exercice->id);
        $actRows = $rows->where('activite', true)->where('activite_id', $this->activite->id);

        $this->assertCount(2, $actRows);
        $this->assertEquals(['IND-A', 'IND-B'], $actRows->pluck('iov_code')->sort()->values()->all());
    }

    public function test_rbm_gar_taux_financier_reel_depuis_ligne(): void
    {
        $this->creerIndicateur('IND-1', 'Unique');

        $row = app(ReportingService::class)->rbmGar($this->exercice->id)
            ->first(fn ($r) => $r['activite'] && $r['premiere_ligne']);

        $this->assertSame(250.0, $row['paye']);
        $this->assertSame(400.0, $row['engage']);
        $this->assertSame(25.0, $row['taux_financier']);
        $this->assertSame(
            app(PapPerformanceService::class)->tauxFinancierReel($this->activite->fresh('ligneBudgetaire')),
            $row['taux_financier']
        );
    }

    public function test_rapport_composer_rbm_gar_inclut_synthese_et_iov(): void
    {
        $this->creerIndicateur('IND-X', 'Indicateur X');

        $sections = app(RapportComposer::class)->composer('rbm_gar', $this->exercice, [])['sections'];

        $this->assertSame('kpi', $sections[0]['type']);
        $this->assertStringContainsString('financier réel', $sections[0]['titre']);
        $this->assertSame('table', $sections[1]['type']);
        $this->assertStringContainsString('IOV', $sections[1]['titre']);

        $tableRows = $sections[1]['lignes'];
        $this->assertTrue(collect($tableRows)->contains(fn ($l) => str_contains((string) ($l[2] ?? ''), 'Indicateur X')));
    }

    public function test_rbm_gar_synthese_compte_iov_rouges(): void
    {
        $ind = $this->creerIndicateur('IND-R', 'Alerte');
        Mesure::create([
            'indicateur_id' => $ind->id,
            'periode' => '2026-T1',
            'valeur_realisee' => 10,
            'taux_atteinte' => 10,
            'feu' => 'rouge',
            'statut' => 'validee',
        ]);

        $synthese = app(ReportingService::class)->rbmGarSynthese($this->exercice->id);

        $this->assertGreaterThanOrEqual(1, $synthese['nb_iov_rouge']);
        $this->assertSame(25.0, $synthese['taux_financier']);
    }
}
