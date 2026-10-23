<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P1 — Théorie du changement (RBM) : vue narrative + PDF + archivage gouverné de la chaîne causale
 * Impact→Effets→Produits→Activités, construite sur le référentiel RBM existant (aucune nouvelle entité).
 */
class TheorieChangementTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        Storage::fake('local');
    }

    private function user(array $permissions): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_vue_accessible_avec_rbm_voir(): void
    {
        $this->actingAs($this->user(['rbm.voir']))
            ->get(route('rbm.theorie-changement', $this->exercice))
            ->assertOk()
            ->assertSee('Théorie du changement')
            ->assertSee('Impact');
    }

    public function test_vue_refusee_sans_permission(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true]))
            ->get(route('rbm.theorie-changement', $this->exercice))
            ->assertForbidden();
    }

    public function test_pdf_telechargeable(): void
    {
        $this->actingAs($this->user(['rbm.generer_pdf']))
            ->get(route('rbm.pdf.theorie-changement', $this->exercice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_archivage_gouverne_de_la_theorie_du_changement(): void
    {
        $this->actingAs($this->user(['rbm.generer_pdf']))
            ->get(route('rbm.pdf.archiver', [$this->exercice, 'theorie-changement']))
            ->assertRedirect();

        $rapport = Rapport::where('type', 'export_rbm_theorie_changement_pdf')->firstOrFail();
        $this->assertSame('pdf', $rapport->format);
        $this->assertSame($this->exercice->id, $rapport->exercice_id);
        Storage::disk('local')->assertExists($rapport->chemin);
    }
}
