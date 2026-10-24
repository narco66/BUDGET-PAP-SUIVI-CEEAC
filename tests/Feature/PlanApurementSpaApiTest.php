<?php

namespace Tests\Feature;

use App\Models\AppelFonds;
use App\Models\EcheanceApurement;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\PlanApurement;
use App\Models\User;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanApurementSpaApiTest extends TestCase
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
        $this->etat = EtatMembre::create(['code' => 'COD', 'pays' => 'RDC', 'cle_repartition' => 15, 'actif' => true]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_lecteur_voit_sans_etablir_ni_regler(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $this->actingAs($gestionnaire)->postJson(route('api.appels-fonds.store'), [
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-APUR-L',
            'objet' => 'Contribution',
            'montant_appele' => 9000000,
            'date_emission' => '2026-01-10',
        ])->assertCreated();

        $appel = AppelFonds::firstWhere('reference', 'AF-APUR-L');

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.plans-apurement.store', $appel), [
                'nombre_echeances' => 3,
                'date_debut' => '2026-03-01',
            ])
            ->assertForbidden();

        $this->actingAs($gestionnaire)
            ->postJson(route('api.plans-apurement.store', $appel), [
                'nombre_echeances' => 3,
                'date_debut' => '2026-03-01',
            ])
            ->assertCreated();

        $plan = PlanApurement::first();
        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.plans-apurement.show', $plan))
            ->assertOk()
            ->assertJsonPath('data.montant_total', '9000000.00')
            ->assertJsonPath('data.actions.annuler', false)
            ->assertJsonPath('data.echeances.0.actions.regler', false);

        $ech = EcheanceApurement::first();
        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.echeances-apurement.regler', $ech), ['montant' => 100])
            ->assertForbidden();
    }

    public function test_parcours_etablissement_plafond_report_et_verrou(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $this->actingAs($gestionnaire)->postJson(route('api.appels-fonds.store'), [
            'exercice_id' => $this->exercice->id,
            'etat_membre_id' => $this->etat->id,
            'reference' => 'AF-APUR-1',
            'objet' => 'Contribution statutaire',
            'montant_appele' => 9000000,
            'date_emission' => '2026-01-10',
            'date_echeance' => '2026-02-01',
        ])->assertCreated();

        $appel = AppelFonds::firstWhere('reference', 'AF-APUR-1');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.appels-fonds.show', $appel))
            ->assertOk()
            ->assertJsonPath('data.actions.etablir_plan', true)
            ->assertJsonPath('data.plan_actif', null);

        $this->actingAs($gestionnaire)->postJson(route('api.plans-apurement.store', $appel), [
            'nombre_echeances' => 3,
            'date_debut' => '2026-03-01',
        ])->assertCreated()
            ->assertJsonPath('code', 'etabli')
            ->assertJsonPath('plan.statut', 'actif')
            ->assertJsonPath('plan.montant_total', '9000000.00')
            ->assertJsonPath('plan.echeances.2.montant_prevu', '3000000.00');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.plans-apurement.store', $appel->fresh()), [
                'nombre_echeances' => 2,
                'date_debut' => '2026-04-01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');

        $plan = PlanApurement::first();
        $ech = $plan->echeances()->orderBy('rang')->first();
        $cle = 'apur-reg-1';

        $this->actingAs($gestionnaire)
            ->withHeaders(['Idempotency-Key' => $cle])
            ->postJson(route('api.echeances-apurement.regler', $ech), ['montant' => 3000000])
            ->assertOk()
            ->assertJsonPath('plan.montant_regle', '3000000.00')
            ->assertJsonPath('plan.echeances.0.statut', 'honoree');

        $this->actingAs($gestionnaire)
            ->withHeaders(['Idempotency-Key' => $cle])
            ->postJson(route('api.echeances-apurement.regler', $ech->fresh()), ['montant' => 3000000])
            ->assertOk()
            ->assertJsonPath('code', 'regle')
            ->assertJsonPath('plan.montant_regle', '3000000.00');

        $this->actingAs($gestionnaire)
            ->flushHeaders()
            ->postJson(route('api.echeances-apurement.regler', $ech->fresh()), ['montant' => 1])
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.plans-apurement.annuler', $plan->fresh()))
            ->assertForbidden();

        $this->actingAs($gestionnaire)
            ->getJson(route('api.appels-fonds.show', $appel->fresh()))
            ->assertOk()
            ->assertJsonPath('data.montant_regle', '3000000.00')
            ->assertJsonPath('data.plan_actif.reference', $plan->reference)
            ->assertJsonPath('data.actions.etablir_plan', false);

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.plans-apurement.show', $plan->fresh()))
            ->assertOk()
            ->assertJsonPath('data.solde', '6000000.00');
    }
}
