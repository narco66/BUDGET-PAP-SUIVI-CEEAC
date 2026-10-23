<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\Programme;
use App\Models\SousProduit;
use App\Models\Structure;
use App\Services\Rbm\RbmAuditService;
use App\Services\Rbm\RbmReferentielService;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase E7 — commande rbm:audit et tests raisonsBlocage. */
class RbmPhaseE7Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private RbmReferentielService $referentiel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);
        $this->seed(RbmReferentielSeeder::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $this->referentiel = app(RbmReferentielService::class);
    }

    private function chaine(): array
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $act = Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $prod->id,
            'code' => 'ACT1',
            'intitule' => 'Act',
            'montant_total' => 1000,
        ]);

        return compact('pilier', 'axe', 'prod', 'sp', 'act');
    }

    public function test_commande_rbm_audit_json(): void
    {
        $this->chaine();
        Pap::create(['exercice_id' => $this->exercice->id, 'libelle' => 'PAP', 'statut' => 'brouillon']);

        $this->artisan('rbm:audit', ['--exercice' => $this->exercice->annee, '--json' => true])
            ->assertSuccessful();

        $rapport = app(RbmAuditService::class)->executer($this->exercice->id);
        $this->assertSame($this->exercice->id, $rapport['exercice_id']);
        $this->assertArrayHasKey('anomalies', $rapport);
        $this->assertContains('PILIER_SANS_PAP_ID', collect($rapport['anomalies'])->pluck('code')->all());
    }

    public function test_rbm_audit_detecte_activite_sans_iov(): void
    {
        $this->chaine();

        $codes = collect(app(RbmAuditService::class)->executer($this->exercice->id)['anomalies'])->pluck('code');
        $this->assertTrue($codes->contains('ACTIVITE_SANS_IOV'));
    }

    public function test_archivage_activite_bloquee_si_ligne_budget(): void
    {
        ['act' => $act] = $this->chaine();
        $rubrique = \App\Models\BudgetRubrique::first();

        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-E7',
            'montant_ouvert' => 500000,
            'activite_id' => $act->id,
        ]);

        $raisons = $this->referentiel->raisonsBlocage($act->fresh());
        $this->assertFalse($this->referentiel->peutSupprimer($act));
        $this->assertStringContainsString('lignes budgétaires', implode(' ', $raisons));
    }

    public function test_archivage_pilier_bloque_si_programme(): void
    {
        ['pilier' => $pilier] = $this->chaine();
        $structure = Structure::create([
            'code' => 'STR-E7',
            'libelle' => 'Structure test',
            'type' => 'direction',
            'actif' => true,
        ]);

        Programme::create([
            'code' => 'PRG-E7',
            'intitule' => 'Programme test',
            'structure_id' => $structure->id,
            'pilier_id' => $pilier->id,
        ]);

        $raisons = $this->referentiel->raisonsBlocage($pilier->fresh());
        $this->assertStringContainsString('programmes institutionnels', implode(' ', $raisons));
    }

    public function test_archivage_pilier_bloque_si_enfants(): void
    {
        ['pilier' => $pilier] = $this->chaine();

        $raisons = $this->referentiel->raisonsBlocage($pilier);
        $this->assertStringContainsString('sous-éléments', implode(' ', $raisons));
    }

    public function test_rbm_audit_signale_activites_protegees(): void
    {
        ['act' => $act] = $this->chaine();
        $rubrique = \App\Models\BudgetRubrique::first();
        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-PROT',
            'montant_ouvert' => 100000,
            'activite_id' => $act->id,
        ]);

        $codes = collect(app(RbmAuditService::class)->executer($this->exercice->id)['anomalies'])->pluck('code');
        $this->assertTrue($codes->contains('ACTIVITE_PROTEGEE'));
    }
}
