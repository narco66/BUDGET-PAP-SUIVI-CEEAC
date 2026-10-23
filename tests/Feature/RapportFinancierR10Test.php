<?php

namespace Tests\Feature;

use App\Models\AppelFonds;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Recettes\AppelFondsService;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase R10 — rapport financier consolidé des recettes (PDF + Word). */
class RapportFinancierR10Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $etat = EtatMembre::create(['code' => 'CMR', 'pays' => 'Cameroun', 'cle_repartition' => 10, 'actif' => true]);

        $appel = app(AppelFondsService::class)->emettre([
            'exercice_id' => $this->exercice->id, 'etat_membre_id' => $etat->id,
            'reference' => 'AF-CMR', 'objet' => 'Contribution 2026',
            'montant_appele' => 10_000_000, 'date_emission' => '2026-01-10', 'date_echeance' => '2026-02-01',
        ], User::factory()->create()->assignRole('gestionnaire_budgetaire'));
        app(AppelFondsService::class)->enregistrerRecouvrement($appel, 4_000_000);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_rapport_financier_pdf(): void
    {
        $response = $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->get(route('recettes.rapports.financier.pdf', ['exercice' => $this->exercice->id]));

        $response->assertOk();
        $this->assertStringContainsString('pdf', $response->headers->get('content-type'));
    }

    public function test_rapport_financier_word(): void
    {
        $response = $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->get(route('recettes.rapports.financier.word', ['exercice' => $this->exercice->id]));

        $response->assertOk();
        $this->assertStringContainsString('wordprocessingml', $response->headers->get('content-type'));
        $this->assertStringContainsString('.docx', $response->headers->get('content-disposition'));
    }

    public function test_rapport_financier_refuse_sans_permission(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->get(route('recettes.rapports.financier.pdf'))
            ->assertForbidden();
    }
}
