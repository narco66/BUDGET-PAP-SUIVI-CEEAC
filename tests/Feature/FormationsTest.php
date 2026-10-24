<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Formation;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormationsTest extends TestCase
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

    public function test_demo_seede_les_formations(): void
    {
        $this->assertGreaterThanOrEqual(6, Formation::count());
        $this->assertTrue(Formation::where('type', 'atelier')->exists());
        // Répartition genre cohérente sur les données démo
        $f = Formation::firstWhere('numero', 'FOR-2026-001');
        $this->assertSame($f->nb_participants, $f->nb_hommes + $f->nb_femmes);
    }

    public function test_crud_formation(): void
    {
        $this->actingAs($this->admin)->get(route('formations.index'))->assertOk();

        $this->actingAs($this->admin)->post(route('formations.store'), [
            'exercice_id' => $this->exercice->id, 'intitule' => 'Atelier test', 'type' => 'atelier',
            'nb_participants' => 10, 'nb_hommes' => 6, 'nb_femmes' => 4, 'cout' => 3_000_000, 'statut' => 'realise',
        ])->assertRedirect(route('formations.index'));

        $this->assertDatabaseHas('formations', ['intitule' => 'Atelier test', 'nb_participants' => 10]);
        $f = Formation::firstWhere('intitule', 'Atelier test');
        $this->actingAs($this->admin)->get(route('formations.show', $f))->assertOk();
        $this->actingAs($this->admin)->get(route('formations.edit', $f))->assertOk();
    }

    public function test_rapport_formations(): void
    {
        Storage::fake('local');
        config(['reporting.async.enabled' => false]);
        foreach (['pdf', 'excel', 'word'] as $format) {
            $this->actingAs($this->admin)->get(route('reporting.generer', [
                'type' => 'formations', 'format' => $format, 'exercice' => $this->exercice->id,
            ]))->assertOk();
        }
        $this->assertSame(3, Rapport::where('type', 'formations')->count());
    }

    public function test_permission_creation(): void
    {
        $lecteur = User::firstWhere('email', 'lecteur@ceeac.test');
        $this->actingAs($lecteur)->get(route('formations.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('formations.create'))->assertForbidden();
    }

    public function test_api_session_formations(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('api.formations.store'), [
                'exercice_id' => $this->exercice->id,
                'intitule' => 'Atelier API SPA',
                'type' => 'atelier',
                'nb_participants' => 10,
                'nb_hommes' => 6,
                'nb_femmes' => 4,
                'cout' => '3000000.00',
                'statut' => 'realise',
            ])
            ->assertCreated()
            ->assertJsonPath('formation.cout', '3000000.00');

        $this->actingAs(User::firstWhere('email', 'lecteur@ceeac.test'))
            ->postJson(route('api.formations.store'), [
                'exercice_id' => $this->exercice->id,
                'intitule' => 'Interdit',
                'type' => 'atelier',
                'nb_participants' => 1,
                'nb_hommes' => 1,
                'nb_femmes' => 0,
                'cout' => '1.00',
                'statut' => 'planifie',
            ])
            ->assertForbidden();
    }
}
