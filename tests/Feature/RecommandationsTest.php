<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\Recommandation;
use App\Models\User;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecommandationsTest extends TestCase
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

    public function test_demo_seede_les_recommandations(): void
    {
        $this->assertGreaterThanOrEqual(8, Recommandation::count());
        $this->assertTrue(Recommandation::where('source', 'conference_chefs_etat')->exists());
        $this->assertTrue(Recommandation::where('source', 'mission_audit')->exists());
    }

    public function test_crud_recommandation(): void
    {
        $this->actingAs($this->admin)->get(route('recommandations.index'))->assertOk();

        $this->actingAs($this->admin)->post(route('recommandations.store'), [
            'exercice_id' => $this->exercice->id, 'source' => 'conseil_ministres',
            'libelle' => 'Recommandation test', 'priorite' => 'haute',
            'statut' => 'en_cours', 'taux_execution' => 25,
        ])->assertRedirect(route('recommandations.index'));

        $this->assertDatabaseHas('recommandations', ['libelle' => 'Recommandation test', 'taux_execution' => 25]);
        $rec = Recommandation::firstWhere('libelle', 'Recommandation test');
        $this->actingAs($this->admin)->get(route('recommandations.show', $rec))->assertOk();
        $this->actingAs($this->admin)->get(route('recommandations.edit', $rec))->assertOk();
    }

    public function test_rapport_recommandations(): void
    {
        Storage::fake('local');
        foreach (['pdf', 'excel', 'word'] as $format) {
            $this->actingAs($this->admin)->get(route('reporting.generer', [
                'type' => 'recommandations', 'format' => $format, 'exercice' => $this->exercice->id,
            ]))->assertOk();
        }
        $this->assertSame(3, Rapport::where('type', 'recommandations')->count());
    }

    public function test_permission_creation(): void
    {
        $lecteur = User::firstWhere('email', 'lecteur@ceeac.test');
        $this->actingAs($lecteur)->get(route('recommandations.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('recommandations.create'))->assertForbidden();
    }

    public function test_api_session_recommandations(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('api.recommandations.store'), [
                'exercice_id' => $this->exercice->id,
                'source' => 'conseil_ministres',
                'libelle' => 'Reco API SPA',
                'priorite' => 'haute',
                'statut' => 'en_cours',
                'taux_execution' => '25.00',
            ])
            ->assertCreated()
            ->assertJsonPath('recommandation.taux_execution', '25.00');

        $this->actingAs(User::firstWhere('email', 'lecteur@ceeac.test'))
            ->postJson(route('api.recommandations.store'), [
                'exercice_id' => $this->exercice->id,
                'source' => 'autre',
                'libelle' => 'Interdit',
                'priorite' => 'basse',
                'statut' => 'en_attente',
                'taux_execution' => '0',
            ])
            ->assertForbidden();
    }
}
