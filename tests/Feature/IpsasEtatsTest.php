<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Immobilisation;
use App\Services\Reporting\RapportComposer;
use App\Services\Reporting\RapportIpsasService;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P3-10 — États IPSAS complets (bilan, compte de résultat, annexes). */
class IpsasEtatsTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(SafeDatabaseSeeder::class);
        $this->exercice = Exercice::firstWhere('annee', 2026);
    }

    public function test_donnees_preparatoires_contient_bilan_et_compte_resultat(): void
    {
        $donnees = app(RapportIpsasService::class)->donneesPreparatoires($this->exercice);

        $this->assertSame('IPSAS-COMPLET', $donnees['reference_note']);
        $this->assertArrayHasKey('bilan', $donnees);
        $this->assertArrayHasKey('compte_resultat', $donnees);
        $this->assertArrayHasKey('actif', $donnees['bilan']);
        $this->assertArrayHasKey('passif', $donnees['bilan']);
        $this->assertGreaterThan(0, $donnees['bilan']['total_actif']);
        $this->assertEqualsWithDelta(
            $donnees['bilan']['total_actif'],
            $donnees['bilan']['total_passif'],
            0.01
        );
    }

    public function test_bilan_inclut_immobilisations_vnc(): void
    {
        $service = app(RapportIpsasService::class);
        $donnees = $service->donneesPreparatoires($this->exercice);

        $vncAttendue = Immobilisation::query()
            ->where('exercice_id', $this->exercice->id)
            ->whereIn('statut', ['en_service', 'en_maintenance'])
            ->get()
            ->sum(fn (Immobilisation $i) => $i->valeurNette());

        $this->assertEqualsWithDelta($vncAttendue, $donnees['immobilisations_vnc'], 0.01);

        $ligneImmo = collect($donnees['bilan']['actif'])
            ->firstWhere('libelle', 'Immobilisations corporelles et incorporelles (VNC)');
        $this->assertNotNull($ligneImmo);
        $this->assertEqualsWithDelta($vncAttendue, $ligneImmo['montant'], 0.01);
    }

    public function test_compte_resultat_coherent_avec_produits_et_charges(): void
    {
        $donnees = app(RapportIpsasService::class)->donneesPreparatoires($this->exercice);
        $resultat = $donnees['compte_resultat'];

        $produitsRecettes = (float) $donnees['recettes']['montant_realise'];
        $produitsContributions = (float) $donnees['mobilisation']['recouvre'];
        $chargesPayees = (float) $donnees['budget']['paye'];

        $this->assertEqualsWithDelta(
            round($produitsRecettes + $produitsContributions - $chargesPayees, 2),
            $resultat['resultat_net'],
            0.01
        );
    }

    public function test_sections_ipsas_ordre_etats_puis_annexes(): void
    {
        $sections = app(RapportComposer::class)->composer('ipsas_etat_financier', $this->exercice)['sections'];
        $titres = collect($sections)->pluck('titre')->values()->all();

        $idxBilanActif = array_search('État 1 — Bilan (Actif)', $titres, true);
        $idxResultat = array_search('État 3 — Compte de résultat', $titres, true);
        $idxAnnexe1 = array_search('Annexe 1 — Exécution par nature budgétaire (SYSCOHADA / Titres)', $titres, true);

        $this->assertNotFalse($idxBilanActif);
        $this->assertNotFalse($idxResultat);
        $this->assertNotFalse($idxAnnexe1);
        $this->assertLessThan($idxResultat, $idxBilanActif);
        $this->assertLessThan($idxAnnexe1, $idxResultat);
    }
}
