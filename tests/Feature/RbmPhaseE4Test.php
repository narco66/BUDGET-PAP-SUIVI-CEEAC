<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\User;
use App\Support\RbmAuthorization;
use App\Support\RbmStatutFiltre;
use Database\Seeders\RbmGranularPermissionsSeeder;
use Database\Seeders\RbmPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase E4 — UI archivage/restauration et filtres statut RBM. */
class RbmPhaseE4Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmPermissionsSeeder::class);
        $this->seed(RbmGranularPermissionsSeeder::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    private function chaine(): array
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'P', 'statut' => 'actif']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A', 'statut' => 'actif']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR', 'statut' => 'actif']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP', 'statut' => 'actif']);
        $act = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $prod->id,
            'code' => 'ACT1', 'intitule' => 'Act', 'montant_total' => 1000, 'statut' => 'actif',
        ]);

        return compact('pilier', 'axe', 'prod', 'sp', 'act');
    }

    public function test_archivage_http_marque_noeud_archive(): void
    {
        ['axe' => $axe] = $this->chaine();

        $this->actingAs($this->admin())
            ->post(route('rbm.archiver', ['axe', $axe->id]), ['exercice' => $this->exercice->id])
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertSame('archive', $axe->fresh()->statut);
        $this->assertDatabaseHas('rbm_historiques', [
            'historisable_type' => Axe::class,
            'historisable_id' => $axe->id,
            'action' => 'archive',
        ]);
    }

    public function test_restauration_noeud_archive(): void
    {
        ['prod' => $prod] = $this->chaine();
        $prod->update(['statut' => 'archive']);

        $this->actingAs($this->admin())
            ->post(route('rbm.restaurer', ['produit', $prod->id]), ['exercice' => $this->exercice->id])
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertSame('actif', $prod->fresh()->statut);
    }

    public function test_filtre_statut_masque_noeuds_archives_par_defaut(): void
    {
        ['axe' => $axe] = $this->chaine();
        $axe->update(['statut' => 'archive']);

        $this->actingAs($this->admin())
            ->get(route('rbm.arbre', $this->exercice))
            ->assertOk()
            ->assertDontSee('rbm-code">A1<', false);

        $this->actingAs($this->admin())
            ->get(route('rbm.arbre', $this->exercice).'?statut=archive')
            ->assertOk()
            ->assertSee('A1');
    }

    public function test_arbre_affiche_boutons_archiver_et_restauration(): void
    {
        ['axe' => $axe] = $this->chaine();

        $this->actingAs($this->admin())
            ->get(route('rbm.arbre', $this->exercice))
            ->assertOk()
            ->assertSee('bi-archive', false)
            ->assertSee(route('rbm.archiver', ['axe', $axe->id]), false);

        $axe->update(['statut' => 'archive']);

        $this->actingAs($this->admin())
            ->get(route('rbm.arbre', $this->exercice).'?statut=archive')
            ->assertOk()
            ->assertSee('bi-arrow-counterclockwise', false)
            ->assertSee(route('rbm.restaurer', ['axe', $axe->id]), false);
    }

    public function test_permission_granulaire_activite_gerer_autorise_creation(): void
    {
        ['sp' => $sp] = $this->chaine();
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo(['rbm.voir', 'activite.gerer']);

        $this->assertTrue(RbmAuthorization::peut($user, 'activite', 'creer'));
        $this->assertTrue(RbmAuthorization::peut($user, 'activite', 'archiver'));
        $this->assertFalse(RbmAuthorization::peut($user, 'pilier', 'creer'));

        $this->actingAs($user)
            ->get(route('rbm.create', 'activite').'?parent='.$sp->id.'&exercice='.$this->exercice->id)
            ->assertOk();
    }

    public function test_filtre_statut_helper_affiche_chemin_vers_archive(): void
    {
        $nodes = $this->chaine();
        $nodes['axe']->update(['statut' => 'archive']);

        $this->assertTrue(RbmStatutFiltre::visible($nodes['pilier'], 'pilier', 'archive'));
        $this->assertFalse(RbmStatutFiltre::visible($nodes['axe'], 'axe', 'actif'));
    }
}
