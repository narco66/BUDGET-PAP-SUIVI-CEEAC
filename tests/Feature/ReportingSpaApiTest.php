<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function rapport(User $auteur): Rapport
    {
        return Rapport::create([
            'reference' => 'RPT-SPA-000001',
            'type' => 'pap',
            'titre' => 'Performance PAP SPA',
            'exercice_id' => $this->exercice->id,
            'format' => 'pdf',
            'chemin' => 'rapports/spa-test.pdf',
            'qr_token' => (string) Str::uuid(),
            'statut' => 'genere',
            'genere_par' => $auteur->id,
        ]);
    }

    public function test_lecteur_sans_reporting_est_refuse(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->getJson(route('api.reporting.hub'))
            ->assertForbidden();
    }

    public function test_hub_liste_et_fiche_sans_chemin(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $doc = $this->rapport($gestionnaire);

        $this->actingAs($gestionnaire)
            ->getJson(route('api.reporting.hub'))
            ->assertOk()
            ->assertJsonPath('indicateurs.total', 1)
            ->assertJsonPath('types.0.type', 'pap')
            ->assertJsonPath('recents.0.reference', 'RPT-SPA-000001')
            ->assertJsonMissingPath('recents.0.chemin');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.reporting.rapports', ['type' => 'pap']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $doc->id);

        $fiche = $this->actingAs($gestionnaire)
            ->getJson(route('api.reporting.rapport', $doc))
            ->assertOk()
            ->assertJsonPath('data.titre', 'Performance PAP SPA')
            ->assertJsonMissingPath('data.chemin');

        $this->assertStringContainsString(
            '/reporting/rapports/'.$doc->id.'/telecharger',
            (string) $fiche->json('data.href_telechargement')
        );
    }

    public function test_api_cockpit_et_planification(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $admin = $this->user('administrateur');

        $this->actingAs($this->user('point_focal'))
            ->getJson(route('api.reporting.cockpit', ['exercice' => $this->exercice->id]))
            ->assertForbidden();

        $this->actingAs($gestionnaire)
            ->getJson(route('api.reporting.cockpit', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('data.exercice.id', $this->exercice->id)
            ->assertJsonPath('data.global.ouvert', '0.00')
            ->assertJsonPath('data.retards.synthese.total', 0);

        $this->actingAs($gestionnaire)
            ->getJson(route('api.reporting.planification'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson(route('api.reporting.planification'))
            ->assertOk()
            ->assertJsonPath('data.schedule.enabled', true);
    }
}
