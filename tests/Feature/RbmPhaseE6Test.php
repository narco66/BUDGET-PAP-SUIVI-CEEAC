<?php

namespace Tests\Feature;

use App\Models\Axe;
use App\Models\CadreLogique;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\User;
use Database\Seeders\RbmGranularPermissionsSeeder;
use Database\Seeders\RbmPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase E6 — édition cadre logique + exports dédiés. */
class RbmPhaseE6Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private Axe $axe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmPermissionsSeeder::class);
        $this->seed(RbmGranularPermissionsSeeder::class);

        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'Pilier test']);
        $this->axe = Axe::create([
            'pilier_id' => $pilier->id,
            'code' => 'A1',
            'intitule' => 'Axe test',
            'objectif_strategique' => null,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    private function lecteur(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo(['rbm.voir', 'cadre_logique.voir']);

        return $user;
    }

    public function test_edit_cadre_logique_accessible_avec_permission_gerer(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.cadre-logique.edit', $this->exercice))
            ->assertOk()
            ->assertSee('Objectif stratégique')
            ->assertSee('Axe test');
    }

    public function test_edit_refuse_sans_permission_gerer(): void
    {
        $this->actingAs($this->lecteur())
            ->get(route('rbm.cadre-logique.edit', $this->exercice))
            ->assertForbidden();
    }

    public function test_mise_a_jour_objectif_strategique_et_commentaire(): void
    {
        $this->actingAs($this->admin())
            ->put(route('rbm.cadre-logique.update', $this->exercice), [
                'intitule' => 'CL institutionnel 2026',
                'description' => 'Document stratégique CEEAC',
                'axes' => [[
                    'id' => $this->axe->id,
                    'objectif_strategique' => 'Renforcer la gouvernance régionale',
                    'commentaire' => 'Hypothèse : stabilité politique',
                ]],
            ])
            ->assertRedirect(route('rbm.cadre-logique', $this->exercice))
            ->assertSessionHas('succes');

        $this->assertSame('Renforcer la gouvernance régionale', $this->axe->fresh()->objectif_strategique);

        $doc = CadreLogique::where('exercice_id', $this->exercice->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame('CL institutionnel 2026', $doc->intitule);
        $this->assertSame('Hypothèse : stabilité politique', $doc->elements['commentaires_axes'][(string) $this->axe->id]);
    }

    public function test_vue_cadre_affiche_bouton_modifier_pour_gerant(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.cadre-logique', $this->exercice))
            ->assertOk()
            ->assertSee('Modifier')
            ->assertSee('Excel')
            ->assertSee('PDF');
    }

    public function test_export_excel_cadre_logique(): void
    {
        $this->axe->update(['objectif_strategique' => 'Objectif exporté']);

        $this->actingAs($this->admin())
            ->get(route('rbm.export.cadre-logique', $this->exercice))
            ->assertOk();
    }

    public function test_export_pdf_cadre_logique(): void
    {
        $this->actingAs($this->admin())
            ->get(route('rbm.pdf.cadre-logique', $this->exercice))
            ->assertOk();
    }
}
