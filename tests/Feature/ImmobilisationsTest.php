<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Immobilisation;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImmobilisationsTest extends TestCase
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

    public function test_demo_seede_les_immobilisations(): void
    {
        $this->assertGreaterThanOrEqual(5, Immobilisation::count());
        $this->assertTrue(Immobilisation::where('categorie', 'informatique')->exists());
    }

    public function test_crud_immobilisation(): void
    {
        $this->actingAs($this->admin)->get(route('immobilisations.index'))->assertOk();

        $this->actingAs($this->admin)->post(route('immobilisations.store'), [
            'exercice_id' => $this->exercice->id,
            'designation' => 'Imprimante réseau',
            'categorie' => 'informatique',
            'date_acquisition' => '2026-01-15',
            'valeur_acquisition' => 1_500_000,
            'duree_amortissement_ans' => 3,
            'statut' => 'en_service',
        ])->assertRedirect(route('immobilisations.index'));

        $this->assertDatabaseHas('immobilisations', ['designation' => 'Imprimante réseau']);
        $i = Immobilisation::firstWhere('designation', 'Imprimante réseau');
        $this->actingAs($this->admin)->get(route('immobilisations.show', $i))->assertOk();
        $this->actingAs($this->admin)->get(route('immobilisations.edit', $i))->assertOk();
    }

    public function test_rapport_immobilisations(): void
    {
        Storage::fake('local');
        config(['reporting.async.enabled' => false]);
        foreach (['pdf', 'excel', 'word'] as $format) {
            $this->actingAs($this->admin)->get(route('reporting.generer', [
                'type' => 'immobilisations', 'format' => $format, 'exercice' => $this->exercice->id,
            ]))->assertOk();
        }
        $this->assertSame(3, Rapport::where('type', 'immobilisations')->count());
    }

    public function test_permission_creation(): void
    {
        $lecteur = User::firstWhere('email', 'lecteur@ceeac.test');
        $this->actingAs($lecteur)->get(route('immobilisations.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('immobilisations.create'))->assertForbidden();
    }
}
