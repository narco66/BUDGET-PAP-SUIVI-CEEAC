<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use App\Notifications\Pap\PapWorkflowNotification;
use App\Services\WorkflowService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/** Phase P2 — CRUD enveloppe, workflow DPPB, notifications. */
class PapP2Test extends TestCase
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

    public function test_creer_pap_via_http(): void
    {
        $exercice = Exercice::create(['annee' => 2028, 'libelle' => 'Ex 2028', 'statut' => 'ouvert']);

        $this->actingAs($this->directeur)
            ->post(route('paps.store'), [
                'exercice_id' => $exercice->id,
                'libelle' => 'PAP institutionnel 2028',
                'reference' => 'PAP-2028',
            ])
            ->assertRedirect();

        $pap = Pap::firstWhere('exercice_id', $exercice->id);
        $this->assertNotNull($pap);
        $this->assertSame('PAP-2028', $pap->reference);
        $this->assertSame('brouillon', $pap->statut);
    }

    public function test_modifier_pap_brouillon(): void
    {
        $pap = $this->creerPap();

        $this->actingAs($this->directeur)
            ->put(route('paps.update', $pap), [
                'libelle' => 'PAP modifié',
                'observations' => 'Note test',
            ])
            ->assertRedirect(route('paps.show', $pap));

        $this->assertSame('PAP modifié', $pap->fresh()->libelle);
        $this->assertSame('Note test', $pap->fresh()->observations);
    }

    public function test_suppression_interdite_si_publie(): void
    {
        $pap = $this->creerPap(['statut' => 'publie']);

        $this->actingAs($this->directeur)
            ->delete(route('paps.destroy', $pap))
            ->assertForbidden();
    }

    public function test_suppression_pap_brouillon(): void
    {
        $pap = $this->creerPap();

        $this->actingAs($this->directeur)
            ->delete(route('paps.destroy', $pap))
            ->assertRedirect(route('paps.index'));

        $this->assertSoftDeleted('paps', ['id' => $pap->id]);
    }

    public function test_workflow_dppb_six_etapes(): void
    {
        $pap = $this->creerPap();
        $instance = app(WorkflowService::class)->demarrer($pap, 'PAP', $this->directeur);

        $this->assertCount(6, $instance->etapes);

        foreach (['chef_service', 'directeur', 'commissaire', 'controleur_financier', 'secretaire_general', 'president'] as $role) {
            app(WorkflowService::class)->valider($instance->fresh(), $this->user($role));
        }

        $this->assertSame('publie', $pap->fresh()->statut);
        $this->assertSame('valide', $instance->fresh()->statut);
        $this->assertNotNull($pap->fresh()->validated_at);
    }

    public function test_notification_soumission_pap(): void
    {
        Notification::fake();
        $pap = $this->creerPapAvecActivite();
        $chef = $this->user('chef_service');

        $this->actingAs($this->directeur)
            ->post(route('paps.soumettre', $pap))
            ->assertRedirect();

        Notification::assertSentTo($chef, PapWorkflowNotification::class);
    }

    public function test_president_ne_peut_pas_valider_etape_service(): void
    {
        $pap = $this->creerPap();
        app(WorkflowService::class)->demarrer($pap, 'PAP', $this->directeur);

        $this->actingAs($this->user('president'))
            ->post(route('paps.agir', $pap), ['decision' => 'valider'])
            ->assertForbidden();
    }

    public function test_rejet_enregistre_motif(): void
    {
        $pap = $this->creerPap();
        app(WorkflowService::class)->demarrer($pap, 'PAP', $this->directeur);

        $this->actingAs($this->user('chef_service'))
            ->post(route('paps.agir', $pap), [
                'decision' => 'rejeter',
                'observation' => 'Données incomplètes sur le pilier 3.',
            ])
            ->assertRedirect();

        $this->assertSame('rejete', $pap->fresh()->statut);
        $this->assertStringContainsString('pilier 3', $pap->fresh()->motif_rejet);
    }

    private function creerPap(array $attrs = []): Pap
    {
        $exercice = Exercice::create(['annee' => 2027, 'libelle' => 'Ex 2027', 'statut' => 'ouvert']);

        return Pap::create(array_merge([
            'exercice_id' => $exercice->id,
            'reference' => 'PAP-2027',
            'libelle' => 'PAP test',
            'statut' => 'brouillon',
            'date_elaboration' => now()->toDateString(),
        ], $attrs));
    }

    private function creerPapAvecActivite(): Pap
    {
        $pap = $this->creerPap();
        $pilier = Pilier::create(['exercice_id' => $pap->exercice_id, 'code' => 'P1', 'intitule' => 'Pilier', 'pap_id' => $pap->id]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT1', 'intitule' => 'Activité test', 'montant_total' => 0,
        ]);

        return $pap;
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }
}
