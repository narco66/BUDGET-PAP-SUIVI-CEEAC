<?php

namespace Tests\Feature;

use App\Models\AppelFonds;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\User;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppelsFondsSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private EtatMembre $etat;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->etat = EtatMembre::create(['code' => 'CMR', 'pays' => 'Cameroun', 'cle_repartition' => 10, 'actif' => true]);
        Notification::fake();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_lecteur_liste_sans_emettre_ni_recouvrer(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $this->actingAs($gestionnaire)->postJson(route('api.appels-fonds.store'), [
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-LEC',
            'objet' => 'Contribution',
            'montant_appele' => 1000000,
            'date_emission' => '2026-01-15',
        ])->assertCreated();

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.appels-fonds.index', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('data.0.montant_appele', '1000000.00')
            ->assertJsonPath('data.0.actions.recouvrer', false);

        $appel = AppelFonds::first();
        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.appels-fonds.store'), [
                'exercice_id' => $this->exercice->id,
                'reference' => 'AF-X',
                'objet' => 'Interdit',
                'montant_appele' => 100,
                'date_emission' => '2026-01-15',
            ])
            ->assertForbidden();

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.appels-fonds.recouvrer', $appel), ['montant' => 10])
            ->assertForbidden();
    }

    public function test_parcours_emission_relance_recouvrement_et_plafond(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.appels-fonds.options'))
            ->assertOk()
            ->assertJsonPath('etats.0.pays', 'Cameroun');

        $this->actingAs($gestionnaire)->postJson(route('api.appels-fonds.store'), [
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-SPA-1',
            'objet' => 'Contribution statutaire',
            'montant_appele' => 10000000,
            'date_emission' => '2026-01-15',
            'date_echeance' => '2026-02-01',
            'taux_penalite' => 5,
        ])->assertCreated()
            ->assertJsonPath('appel.statut', 'emis')
            ->assertJsonPath('appel.solde', '10000000.00');

        $appel = AppelFonds::firstWhere('reference', 'AF-SPA-1');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.appels-fonds.relancer', $appel), ['canal' => 'courrier', 'objet' => 'Rappel'])
            ->assertOk()
            ->assertJsonPath('code', 'relance');

        $cle = 'af-rec-1';
        $this->actingAs($gestionnaire)
            ->withHeaders(['Idempotency-Key' => $cle])
            ->postJson(route('api.appels-fonds.recouvrer', $appel->fresh()), ['montant' => 4000000])
            ->assertOk()
            ->assertJsonPath('appel.statut', 'partiellement_recouvre')
            ->assertJsonPath('appel.solde', '6000000.00');

        $this->actingAs($gestionnaire)
            ->withHeaders(['Idempotency-Key' => $cle])
            ->postJson(route('api.appels-fonds.recouvrer', $appel->fresh()), ['montant' => 4000000])
            ->assertOk()
            ->assertJsonPath('code', 'recouvre')
            ->assertJsonPath('appel.solde', '6000000.00');

        $this->actingAs($gestionnaire)
            ->flushHeaders()
            ->postJson(route('api.appels-fonds.recouvrer', $appel->fresh()), ['montant' => 9000000])
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.appels-fonds.annuler', $appel->fresh()))
            ->assertForbidden();

        $this->actingAs($gestionnaire)
            ->postJson(route('api.appels-fonds.recouvrer', $appel->fresh()), ['montant' => 6000000])
            ->assertOk()
            ->assertJsonPath('appel.statut', 'solde');

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.appels-fonds.show', $appel->fresh()))
            ->assertOk()
            ->assertJsonPath('data.montant_regle', '10000000.00')
            ->assertJsonPath('data.relances.0.montant_du', '10000000.00');
    }
}
