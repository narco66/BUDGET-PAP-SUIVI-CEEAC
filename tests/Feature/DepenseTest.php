<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

class DepenseTest extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private Exercice $exercice;
    private EngagementService $service;
    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
        $this->seed(DepensePermissionsSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->service = app(EngagementService::class);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function ligne(string $code, float $ouvert): LigneBudgetaire
    {
        return $this->activiteEtLigne($this->exercice, $code, $ouvert)[1];
    }

    private function creer(array $imputations, string $role = 'gestionnaire_budgetaire'): Engagement
    {
        $user = $this->user($role);
        $ligne = LigneBudgetaire::with('activite')->find($imputations[0]['ligne_budgetaire_id']);
        $activite = $ligne->activite;
        $montant = array_sum(array_column($imputations, 'montant'));
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, $montant, 'EB-'.uniqid());

        return $this->service->creer(
            [
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'type' => 'simple',
                'objet' => 'Test',
            ],
            $imputations,
            $user,
        );
    }

    public function test_creation_calcule_le_total_sans_reserver(): void
    {
        $a = $this->ligne('LB-A', 1000);
        $e = $this->creer([['ligne_budgetaire_id' => $a->id, 'montant' => 600]]);

        $this->assertEquals(600, (float) $e->montant_total);
        $this->assertSame('brouillon', $e->statut);
        $this->assertFalse($e->credits_reserves);
        $this->assertEquals(0, (float) $a->fresh()->montant_engage); // pas encore réservé
    }

    public function test_soumission_reserve_les_credits_et_demarre_le_visa(): void
    {
        $a = $this->ligne('LB-A', 1000);
        $e = $this->creer([['ligne_budgetaire_id' => $a->id, 'montant' => 600]]);

        $this->service->soumettre($e, $this->user('gestionnaire_budgetaire'));

        $this->assertEquals(600, (float) $a->fresh()->montant_engage);
        $this->assertTrue($e->fresh()->credits_reserves);
        $this->assertSame('soumis', $e->fresh()->statut);
        $this->assertNotNull($e->fresh()->workflowActif);
    }

    public function test_soumission_refusee_si_credits_insuffisants(): void
    {
        $a = $this->ligne('LB-A', 500);
        $e = $this->creer([['ligne_budgetaire_id' => $a->id, 'montant' => 600]]);

        try {
            $this->service->soumettre($e, $this->user('gestionnaire_budgetaire'));
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException) {
            // attendu
        }

        $this->assertEquals(0, (float) $a->fresh()->montant_engage);
        $this->assertFalse($e->fresh()->credits_reserves);
        $this->assertSame('en_attente_arbitrage', $e->fresh()->statut);
        $this->assertNotEmpty($e->fresh()->diagnostic_credits);
    }

    public function test_reservation_multi_lignes_est_atomique(): void
    {
        $a = $this->ligne('LB-A', 1000); // suffisante
        $b = $this->ligne('LB-B', 100);  // insuffisante
        $e = $this->creer([
            ['ligne_budgetaire_id' => $a->id, 'montant' => 600],
            ['ligne_budgetaire_id' => $b->id, 'montant' => 600],
        ]);

        try {
            $this->service->soumettre($e, $this->user('gestionnaire_budgetaire'));
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException) {
        }

        // rollback : AUCUNE ligne ne doit être impactée
        $this->assertEquals(0, (float) $a->fresh()->montant_engage);
        $this->assertEquals(0, (float) $b->fresh()->montant_engage);
    }

    public function test_visa_du_controleur_passe_a_vise(): void
    {
        $a = $this->ligne('LB-A', 1000);
        $e = $this->creer([['ligne_budgetaire_id' => $a->id, 'montant' => 600]]);
        $this->service->soumettre($e, $this->user('gestionnaire_budgetaire'));

        $this->viserEngagement($e);

        $this->assertSame('vise', $e->fresh()->statut);
        $this->assertEquals(600, (float) $a->fresh()->montant_engage); // crédits maintenus
    }

    public function test_rejet_du_controleur_libere_les_credits(): void
    {
        $a = $this->ligne('LB-A', 1000);
        $e = $this->creer([['ligne_budgetaire_id' => $a->id, 'montant' => 600]]);
        $this->service->soumettre($e, $this->user('gestionnaire_budgetaire'));

        $this->workflow->rejeter($e->workflowActif, $this->user('directeur'), 'Pièces manquantes.', 'documentaire');

        $this->assertSame('rejete', $e->fresh()->statut);
        $this->assertEquals(0, (float) $a->fresh()->montant_engage); // crédits libérés
        $this->assertFalse($e->fresh()->credits_reserves);
    }

    public function test_separation_des_fonctions_le_createur_ne_vise_pas(): void
    {
        $createur = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-SEP', 1000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $createur, 600, 'EB-SEP');
        $e = $this->service->creer(
            [
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'type' => 'simple',
                'objet' => 'T',
            ],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 600]],
            $createur,
        );
        $this->service->soumettre($e, $createur);

        // le gestionnaire (créateur) n'a pas le rôle controleur_financier
        $this->assertFalse($this->workflow->peutAgir($e->workflowActif, $createur));
    }

    public function test_pdf_engagement_se_genere(): void
    {
        $a = $this->ligne('LB-A', 1000);
        $e = $this->creer([['ligne_budgetaire_id' => $a->id, 'montant' => 600]]);

        $this->actingAs($this->user('administrateur'))
            ->get(route('engagements.pdf', $e))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_upload_piece_justificative(): void
    {
        Storage::fake('local');
        $a = $this->ligne('LB-A', 1000);
        $e = $this->creer([['ligne_budgetaire_id' => $a->id, 'montant' => 600]]);

        $this->actingAs($this->user('administrateur'))
            ->post(route('pieces.store', ['engagement', $e->id]), [
                'fichier' => UploadedFile::fake()->createWithContent('facture.pdf', "%PDF-1.4
1 0 obj<<>>endobj
trailer<<>>
%%EOF
"),
                'categorie' => 'facture',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('pieces_justificatives', [
            'attachable_type' => Engagement::class, 'attachable_id' => $e->id, 'categorie' => 'facture',
        ]);
    }
}
