<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use Database\Seeders\GedSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuiviTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private Activite $activite;
    private CampagneSuivi $campagne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, WorkflowPermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);

        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $this->activite = Activite::create(['sous_produit_id' => $sp->id, 'produit_id' => $prod->id, 'code' => 'ACT1', 'intitule' => 'A', 'montant_total' => 1000]);

        $this->campagne = CampagneSuivi::create(['exercice_id' => $this->exercice->id, 'libelle' => 'T1', 'type' => 'trimestriel', 'periode' => '2026-T1', 'statut' => 'ouverte']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function saisir(string $role = 'point_focal', float $phys = 50, float $realise = 400): Collecte
    {
        $this->actingAs($this->user($role))
            ->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
                'taux_physique' => $phys, 'montant_realise' => $realise,
            ])->assertRedirect();

        return Collecte::first();
    }

    private function attacherJustificatif(Collecte $c): void
    {
        $c->pieces()->create([
            'nom' => 'rapport.pdf',
            'chemin' => 'suivi/test/rapport.pdf',
            'type_mime' => 'application/pdf',
            'taille' => 1024,
            'categorie' => 'justificatif',
            'televerse_par' => $c->saisi_par ?? $this->user('point_focal')->id,
        ]);
    }

    private function validerCollecte(Collecte $c): void
    {
        $this->attacherJustificatif($c);
        $this->actingAs($this->user('chef_service'))->post(route('suivi.collecte.verifier', $c))->assertRedirect();
        $this->actingAs($this->user('controleur_financier'))->post(route('suivi.collecte.valider', $c->fresh()))->assertRedirect();
    }

    public function test_saisie_calcule_taux_financier_et_ecart(): void
    {
        $this->saisir('point_focal', 50, 400);

        $c = Collecte::first();
        $this->assertEquals(40.0, (float) $c->taux_financier);  // 400 / 1000
        $this->assertEquals(10.0, (float) $c->ecart);           // 50 - 40
        $this->assertSame('saisie', $c->statut);
    }

    public function test_validation_reporte_les_taux_sur_l_activite(): void
    {
        $c = $this->saisir('point_focal', 50, 400);
        $this->validerCollecte($c);

        $this->assertSame('validee', $c->fresh()->statut);
        $this->assertEquals(50.0, (float) $this->activite->fresh()->taux_realisation_physique);
        $this->assertEquals(40.0, (float) $this->activite->fresh()->taux_realisation_financier);
    }

    public function test_validation_impossible_sans_verification(): void
    {
        $this->saisir();
        $c = Collecte::first();

        $this->actingAs($this->user('controleur_financier'))
            ->post(route('suivi.collecte.valider', $c))->assertStatus(422);
    }

    public function test_point_focal_ne_peut_pas_valider(): void
    {
        $this->saisir();
        $c = Collecte::first();

        // point_focal a suivi.creer mais pas suivi.valider
        $this->actingAs($this->user('point_focal'))
            ->post(route('suivi.collecte.verifier', $c))->assertForbidden();
    }

    public function test_saisie_refusee_si_campagne_cloturee(): void
    {
        $this->campagne->update(['statut' => 'cloturee']);

        $this->actingAs($this->user('point_focal'))
            ->post(route('suivi.collecte.enregistrer', [$this->campagne, $this->activite]), [
                'taux_physique' => 50, 'montant_realise' => 400,
            ])->assertStatus(422);
    }

    public function test_campagne_accessible_et_consolidation(): void
    {
        $c = $this->saisir('point_focal', 60, 500);
        $this->validerCollecte($c);

        $this->actingAs($this->user('controleur_financier'))
            ->get(route('suivi.show', $this->campagne))
            ->assertOk()
            ->assertSee('Consolidation');
    }

    public function test_api_session_campagnes_suivi(): void
    {
        config(['mfa.enabled' => false]);
        $chef = $this->user('chef_service');

        $this->actingAs($chef)
            ->getJson(route('api.campagnes-suivi.index', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('data.0.periode', '2026-T1');

        $this->actingAs($chef)->postJson(route('api.campagnes-suivi.store'), [
            'exercice_id' => $this->exercice->id,
            'libelle' => 'T2 API',
            'type' => 'trimestriel',
            'periode' => '2026-T2',
        ])->assertCreated()->assertJsonPath('campagne.periode', '2026-T2');

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.campagnes-suivi.store'), [
                'exercice_id' => $this->exercice->id,
                'libelle' => 'Interdit',
                'type' => 'trimestriel',
                'periode' => '2026-T9',
            ])
            ->assertForbidden();
    }

    public function test_api_circuit_collecte_visas(): void
    {
        config(['mfa.enabled' => false]);
        $c = $this->saisir('point_focal', 50, 400);

        $this->actingAs($this->user('point_focal'))
            ->postJson(route('api.collectes.verifier', $c))
            ->assertForbidden();

        $this->actingAs($this->user('controleur_financier'))
            ->postJson(route('api.collectes.valider', $c))
            ->assertStatus(422);

        $this->actingAs($this->user('chef_service'))
            ->postJson(route('api.collectes.verifier', $c))
            ->assertOk()
            ->assertJsonPath('collecte.statut', 'verifiee');

        $this->actingAs($this->user('controleur_financier'))
            ->postJson(route('api.collectes.valider', $c->fresh()))
            ->assertStatus(422);

        $this->attacherJustificatif($c->fresh());

        $this->actingAs($this->user('controleur_financier'))
            ->postJson(route('api.collectes.valider', $c->fresh()))
            ->assertOk()
            ->assertJsonPath('collecte.statut', 'validee')
            ->assertJsonPath('collecte.taux_physique', '50.00');
    }

    public function test_api_justificatif_ged_collecte(): void
    {
        config(['mfa.enabled' => false]);
        $this->seed(GedSeeder::class);
        Storage::fake('local');
        $c = $this->saisir('point_focal', 50, 400);

        $this->actingAs($this->user('chef_service'))
            ->post(route('api.collectes.justificatifs', $c), [
                'fichier' => UploadedFile::fake()->create('rapport.pdf', 40, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($this->user('administrateur'))
            ->post(route('api.collectes.justificatifs', $c), [
                'fichier' => UploadedFile::fake()->create('rapport.pdf', 40, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('document.titre', 'rapport.pdf')
            ->assertJsonPath('collecte.justificatifs.total', 1)
            ->assertJsonPath('collecte.justificatifs.requis', false)
            ->assertJsonMissingPath('document.chemin_fichier')
            ->assertJsonMissingPath('document.hash_sha256');
    }
}
