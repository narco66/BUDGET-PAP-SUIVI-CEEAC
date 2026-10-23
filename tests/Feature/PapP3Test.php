<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\PapHistorique;
use App\Models\PapRisque;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use App\Services\Pap\PapReferentielService;
use App\Services\WorkflowService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase P3 — GAR : risques, mesures correctives, écarts, snapshots complets. */
class PapP3Test extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->directeur = User::factory()->create(['actif' => true])->assignRole('directeur');
    }

    public function test_creer_risque_via_http(): void
    {
        ['pap' => $pap, 'activite' => $activite] = $this->creerPapAvecActivite();

        $this->actingAs($this->directeur)
            ->post(route('paps.risques.store', $pap), [
                'code' => 'R-P3-01',
                'libelle' => 'Retard de décaissement PTF',
                'probabilite' => 'moyenne',
                'impact' => 'eleve',
                'statut' => 'ouvert',
                'activite_id' => $activite->id,
                'mitigation' => 'Relance partenaires techniques',
            ])
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertDatabaseHas('pap_risques', [
            'pap_id' => $pap->id,
            'code' => 'R-P3-01',
            'libelle' => 'Retard de décaissement PTF',
        ]);
    }

    public function test_enregistrer_ecarts_activites(): void
    {
        ['pap' => $pap, 'activite' => $activite] = $this->creerPapAvecActivite();

        $this->actingAs($this->directeur)
            ->post(route('paps.ecarts.store', $pap), [
                'ecarts' => [
                    $activite->id => [
                        'cause_ecart' => 'Retard fournisseur',
                        'mesure_corrective' => 'Relancer le marché sous 15 jours',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('succes');

        $activite->refresh();
        $this->assertSame('Retard fournisseur', $activite->cause_ecart);
        $this->assertSame('Relancer le marché sous 15 jours', $activite->mesure_corrective);
    }

    public function test_publication_cree_snapshot_complet(): void
    {
        ['pap' => $pap] = $this->creerPapAvecActivite();

        PapRisque::create([
            'pap_id' => $pap->id,
            'code' => 'R-SNAP',
            'libelle' => 'Risque test snapshot',
            'probabilite' => 'faible',
            'impact' => 'moyen',
            'statut' => 'ouvert',
        ]);

        $instance = app(WorkflowService::class)->demarrer($pap, 'PAP', $this->directeur);
        foreach (['chef_service', 'directeur', 'commissaire', 'controleur_financier', 'secretaire_general', 'president'] as $role) {
            app(WorkflowService::class)->valider($instance->fresh(), $this->user($role));
        }

        $hist = PapHistorique::where('pap_id', $pap->id)->where('action', 'publication')->first();
        $this->assertNotNull($hist);
        $this->assertTrue($hist->snapshot_complet);
        $this->assertSame('snapshot_complet', $hist->snapshot['meta']['type'] ?? null);
        $this->assertNotEmpty($hist->snapshot['piliers'] ?? []);
        $this->assertCount(1, $hist->snapshot['risques'] ?? []);
    }

    public function test_consulter_snapshot_historique(): void
    {
        ['pap' => $pap] = $this->creerPapAvecActivite();

        $hist = app(PapReferentielService::class)->historiser($pap, 'publication', $this->directeur->id);

        $this->actingAs($this->directeur)
            ->get(route('paps.snapshot', [$pap, $hist]))
            ->assertOk()
            ->assertSee('Snapshot PAP')
            ->assertSee('Complet');
    }

    public function test_gar_interdit_sans_permission(): void
    {
        ['pap' => $pap] = $this->creerPapAvecActivite();
        $lecteur = User::factory()->create(['actif' => true])->assignRole('lecteur');

        $this->actingAs($lecteur)
            ->post(route('paps.risques.store', $pap), [
                'code' => 'R-X',
                'libelle' => 'Test',
                'probabilite' => 'faible',
                'impact' => 'faible',
                'statut' => 'ouvert',
            ])
            ->assertForbidden();
    }

    /** @return array{pap: Pap, activite: Activite} */
    private function creerPapAvecActivite(): array
    {
        $exercice = Exercice::create(['annee' => 2029, 'libelle' => 'Ex 2029', 'statut' => 'ouvert']);
        $pap = Pap::create([
            'exercice_id' => $exercice->id,
            'reference' => 'PAP-2029',
            'libelle' => 'PAP test P3',
            'statut' => 'brouillon',
            'version' => 1,
            'date_elaboration' => now()->toDateString(),
        ]);
        $pilier = Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P1', 'intitule' => 'Pilier', 'pap_id' => $pap->id]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $prod->id,
            'code' => 'ACT-P3',
            'intitule' => 'Activité P3',
            'montant_total' => 1000,
        ]);

        return ['pap' => $pap, 'activite' => $activite];
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }
}
