<?php

namespace Tests\Feature;

use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\User;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CockpitDepenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin CD', 'email' => 'admin.cd@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
            'doit_changer_mot_de_passe' => false,
        ]);
        $this->admin->assignRole('administrateur');
        $this->exercice = Exercice::firstWhere('annee', 2026) ?? Exercice::first();
    }

    public function test_cockpit_accessible_et_affiche_la_chaine(): void
    {
        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id, 'rubrique_id' => BudgetRubrique::first()->id,
            'code' => 'LB-CD-1', 'montant_ouvert' => 1_000_000,
            'montant_engage' => 400_000, 'montant_liquide' => 300_000,
            'montant_ordonnance' => 200_000, 'montant_paye' => 100_000,
        ]);

        $this->actingAs($this->admin)->get(route('depense.cockpit', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee("Cockpit d'exécution de la dépense", false) // apostrophe non échappée dans le HTML
            ->assertSee('Progression de la chaîne')
            ->assertSee('400 000') // montant engagé formaté
            ->assertSee('Conformité de la chaîne');
    }

    public function test_cockpit_refuse_sans_droit(): void
    {
        $sansDroit = User::create([
            'name' => 'Sans droit', 'email' => 'nodroit.cd@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);

        $this->actingAs($sansDroit)->get(route('depense.cockpit'))->assertForbidden();
    }

    public function test_cockpit_api_session_renvoie_des_montants_decimaux(): void
    {
        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id, 'rubrique_id' => BudgetRubrique::first()->id,
            'code' => 'LB-CD-API', 'montant_ouvert' => 1_000_000,
            'montant_engage' => 400_000, 'montant_liquide' => 300_000,
            'montant_ordonnance' => 200_000, 'montant_paye' => 100_000,
        ]);

        $reponse = $this->actingAs($this->admin)->getJson(route('api.depense.cockpit', ['exercice' => $this->exercice->id]))
            ->assertOk();
        $this->assertSame('400000.00', $reponse->json('data.execution.engage'));
        $this->assertSame('100000.00', $reponse->json('data.execution.paye'));
        $this->assertIsInt($reponse->json('data.audit.score_conformite'));

        $sansDroit = User::create([
            'name' => 'Sans droit API', 'email' => 'nodroit.cd.api@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->actingAs($sansDroit)->getJson(route('api.depense.cockpit'))->assertForbidden();
    }

    public function test_export_consolide_api_session(): void
    {
        LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id, 'rubrique_id' => BudgetRubrique::first()->id,
            'code' => 'LB-CD-XLS', 'montant_ouvert' => 1_000_000,
        ]);

        $this->actingAs($this->admin)->getJson(route('api.depense.cockpit', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('data.exports.0.cle', 'besoins');

        $this->actingAs($this->admin)->get(route('api.depense.exports.telecharger', [
            'cle' => 'consolide',
            'exercice_id' => $this->exercice->id,
        ]))->assertOk()->assertHeader('content-disposition');

        $sansDroit = User::create([
            'name' => 'Sans export', 'email' => 'nodroit.cd.x@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
            'doit_changer_mot_de_passe' => false,
        ]);
        $this->actingAs($sansDroit)->get(route('api.depense.exports.telecharger', ['cle' => 'consolide']))
            ->assertForbidden();
    }
}
