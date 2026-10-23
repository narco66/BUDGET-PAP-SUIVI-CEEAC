<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Exercice;
use App\Models\PrevisionRecette;
use App\Models\Recette;
use App\Models\User;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Phase R2-A — permissions, séparation des fonctions et protection anti-suppression du module Recettes. */
class RecettesSecuriteR2Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_les_neuf_permissions_recette_existent(): void
    {
        foreach (['voir', 'creer', 'modifier', 'supprimer', 'valider', 'recouvrement', 'encaissement', 'exporter', 'admin'] as $action) {
            $this->assertTrue(
                Permission::where('name', "recette.$action")->exists(),
                "Permission recette.$action manquante."
            );
        }
    }

    public function test_separation_des_fonctions_par_role(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $this->assertTrue($gestionnaire->can('recette.creer'));
        $this->assertTrue($gestionnaire->can('recette.recouvrement'));
        $this->assertFalse($gestionnaire->can('recette.valider'));

        $controleur = $this->user('controleur_financier');
        $this->assertTrue($controleur->can('recette.valider'));
        $this->assertFalse($controleur->can('recette.creer'));

        $comptable = $this->user('comptable');
        $this->assertTrue($comptable->can('recette.encaissement'));
        $this->assertFalse($comptable->can('recette.creer'));

        $lecteur = $this->user('lecteur');
        $this->assertTrue($lecteur->can('recette.voir'));
        $this->assertFalse($lecteur->can('recette.modifier'));
    }

    public function test_recette_comptabilisee_ne_peut_pas_etre_supprimee(): void
    {
        $recette = Recette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'REC-TEST-1',
            'montant_prevu' => 1_000_000,
            'montant_realise' => 1_000_000,
            'statut' => 'realisee',
        ]);

        $this->assertTrue($recette->estVerrouillee());

        $this->expectException(RuntimeException::class);
        $recette->delete();
    }

    public function test_recette_simplement_prevue_peut_etre_supprimee(): void
    {
        $recette = Recette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'REC-TEST-2',
            'montant_prevu' => 1_000_000,
            'montant_realise' => 0,
            'statut' => 'prevue',
        ]);

        $this->assertFalse($recette->estVerrouillee());
        $recette->delete();
        $this->assertSoftDeleted($recette);
    }

    public function test_prevision_validee_ne_peut_pas_etre_supprimee(): void
    {
        $prevision = PrevisionRecette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'PREV-TEST-1',
            'montant_prevu' => 5_000_000,
            'statut' => 'valide',
        ]);

        $this->expectException(RuntimeException::class);
        $prevision->delete();
    }

    public function test_prevision_brouillon_peut_etre_supprimee(): void
    {
        $prevision = PrevisionRecette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'PREV-TEST-2',
            'montant_prevu' => 5_000_000,
            'statut' => 'brouillon',
        ]);

        $prevision->delete();
        $this->assertSoftDeleted($prevision);
    }

    public function test_contribution_est_auditee(): void
    {
        $contribution = Contribution::create([
            'exercice_id' => $this->exercice->id,
            'montant_appele' => 2_000_000,
            'montant_realise' => 0,
        ]);

        $this->assertSame(1, $contribution->journaux()->where('action', 'created')->count());
    }
}
