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
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PapSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2031, 'libelle' => 'Ex 2031', 'statut' => 'ouvert']);
        Notification::fake();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function rattacherActivite(Pap $pap): void
    {
        $pilier = Pilier::create([
            'exercice_id' => $pap->exercice_id,
            'code' => 'P1',
            'intitule' => 'Pilier',
            'pap_id' => $pap->id,
        ]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);
        Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $prod->id,
            'code' => 'ACT1',
            'intitule' => 'Activité test',
            'montant_total' => 1000,
        ]);
    }

    public function test_lecteur_liste_sans_pouvoir_creer_ni_soumettre(): void
    {
        $pap = Pap::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'PAP-2031',
            'libelle' => 'PAP lecture',
            'statut' => 'brouillon',
        ]);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.paps.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reference', 'PAP-2031')
            ->assertJsonPath('data.0.actions.soumettre', false)
            ->assertJsonPath('data.0.version', 1);

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.paps.store'), [
                'exercice_id' => $this->exercice->id,
                'libelle' => 'Interdit',
            ])
            ->assertForbidden();

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.paps.soumettre', $pap))
            ->assertForbidden();
    }

    public function test_parcours_api_creation_soumission_et_premiere_etape(): void
    {
        $directeur = $this->user('directeur');
        $chef = $this->user('chef_service');

        $this->actingAs($directeur)
            ->getJson(route('api.paps.options'))
            ->assertOk()
            ->assertJsonPath('reference_suggestion', 'PAP-2031');

        $this->actingAs($directeur)->postJson(route('api.paps.store'), [
            'exercice_id' => $this->exercice->id,
            'libelle' => 'PAP institutionnel 2031',
            'reference' => 'PAP-2031-SPA',
        ])->assertCreated()->assertJsonPath('pap.statut', 'brouillon');

        $pap = Pap::firstWhere('reference', 'PAP-2031-SPA');
        $this->rattacherActivite($pap);

        $this->actingAs($directeur)->putJson(route('api.paps.update', $pap), [
            'libelle' => 'PAP institutionnel 2031 (à jour)',
        ])->assertOk()->assertJsonPath('pap.libelle', 'PAP institutionnel 2031 (à jour)');

        $cle = 'pap-soumettre-1';
        $this->actingAs($directeur)
            ->withHeaders(['Idempotency-Key' => $cle])
            ->postJson(route('api.paps.soumettre', $pap->fresh()))
            ->assertOk()
            ->assertJsonPath('pap.statut', 'en_validation');

        $this->actingAs($directeur)
            ->withHeaders(['Idempotency-Key' => $cle])
            ->postJson(route('api.paps.soumettre', $pap->fresh()))
            ->assertOk()
            ->assertJsonPath('code', 'soumis');

        $this->actingAs($chef)
            ->postJson(route('api.paps.decisions', $pap->fresh()), ['decision' => 'valider'])
            ->assertOk()
            ->assertJsonPath('pap.statut', 'en_validation');

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.paps.pilotage', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('synthese.montant_prevu', '1000.00')
            ->assertJsonPath('piliers.0.budget_prevu', '1000.00');
    }

    public function test_soumission_bloquee_sans_activite_et_revision_apres_publication(): void
    {
        $directeur = $this->user('directeur');
        $pap = Pap::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'PAP-2031-B',
            'libelle' => 'PAP vide',
            'statut' => 'brouillon',
        ]);

        $this->actingAs($directeur)
            ->postJson(route('api.paps.soumettre', $pap))
            ->assertStatus(422)
            ->assertJsonPath('code', 'audit_bloquant');

        $pap->update(['statut' => 'publie']);
        $this->actingAs($directeur)
            ->postJson(route('api.paps.revision', $pap), ['motif' => 'Ajustement des cibles.'])
            ->assertOk()
            ->assertJsonPath('pap.statut', 'en_revision');
    }
}
