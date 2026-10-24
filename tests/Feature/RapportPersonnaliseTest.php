<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\RapportPersonnalise;
use App\Models\User;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** P3-1 — Constructeur de rapports personnalisés. */
class RapportPersonnaliseTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(SafeDatabaseSeeder::class);
        $this->exercice = Exercice::firstWhere('annee', 2026);
        $this->admin = User::firstWhere('email', 'administrateur@ceeac.test');
    }

    public function test_demo_seede_un_modele_personnalise(): void
    {
        $this->assertTrue(RapportPersonnalise::where('code', 'RPT-PERS-001')->exists());
    }

    public function test_crud_modele_personnalise(): void
    {
        $this->actingAs($this->admin)->get(route('reporting.modeles.index'))->assertOk();

        $this->actingAs($this->admin)->post(route('reporting.modeles.store'), [
            'titre' => 'Export engagements test',
            'source' => 'engagements',
            'colonnes' => ['numero', 'objet', 'montant', 'statut'],
            'partage' => true,
        ])->assertRedirect(route('reporting.modeles.index'));

        $modele = RapportPersonnalise::firstWhere('titre', 'Export engagements test');
        $this->assertNotNull($modele);
        $this->actingAs($this->admin)->get(route('reporting.modeles.show', $modele))->assertOk();
    }

    public function test_generation_pdf_et_excel(): void
    {
        Storage::fake('local');
        config(['reporting.async.enabled' => false]);

        $modele = RapportPersonnalise::firstWhere('code', 'RPT-PERS-001');
        foreach (['pdf', 'excel'] as $format) {
            $this->actingAs($this->admin)->get(route('reporting.modeles.generer', [
                'rapportPersonnalise' => $modele,
                'format' => $format,
                'exercice' => $this->exercice->id,
            ]))->assertOk();
        }
        $this->assertSame(2, Rapport::where('type', 'personnalise')->count());
    }

    public function test_lecteur_peut_voir_mais_pas_creer(): void
    {
        $lecteur = User::firstWhere('email', 'lecteur@ceeac.test');
        $this->actingAs($lecteur)->get(route('reporting.modeles.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('reporting.modeles.create'))->assertForbidden();
    }

    public function test_api_session_modeles_personnalises(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('api.reporting.modeles.store'), [
                'titre' => 'Export engagements API',
                'source' => 'engagements',
                'colonnes' => ['numero', 'objet', 'montant', 'statut'],
                'partage' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('modele.source', 'engagements');

        $modele = RapportPersonnalise::firstWhere('titre', 'Export engagements API');
        $this->actingAs($this->admin)
            ->getJson(route('api.reporting.modeles.show', ['rapportPersonnalise' => $modele, 'exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('data.code', $modele->code);

        $this->actingAs(User::firstWhere('email', 'lecteur@ceeac.test'))
            ->postJson(route('api.reporting.modeles.store'), [
                'titre' => 'Interdit',
                'source' => 'engagements',
                'colonnes' => ['numero'],
            ])
            ->assertForbidden();
    }
}
