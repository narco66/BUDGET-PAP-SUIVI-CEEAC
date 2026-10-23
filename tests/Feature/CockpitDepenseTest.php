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
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin CD', 'email' => 'admin.cd@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
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
}
