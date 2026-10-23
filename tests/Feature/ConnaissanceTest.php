<?php

namespace Tests\Feature;

use App\Models\Ged\Connaissance;
use App\Models\User;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P3-3 — Wiki & procédures (base de connaissances GED). */
class ConnaissanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(SafeDatabaseSeeder::class);
        $this->admin = User::firstWhere('email', 'administrateur@ceeac.test');
    }

    public function test_demo_seede_les_connaissances(): void
    {
        $this->assertGreaterThanOrEqual(3, Connaissance::count());
        $this->assertTrue(Connaissance::where('type', 'procedure')->exists());
    }

    public function test_index_accessible(): void
    {
        $this->actingAs($this->admin)->get(route('ged.connaissances.index'))
            ->assertOk()
            ->assertSee('Wiki & procédures', false)
            ->assertSee('CON-PROC-001', false);
    }

    public function test_crud_et_publication(): void
    {
        $this->actingAs($this->admin)->post(route('ged.connaissances.store'), [
            'titre' => 'Guide test budget',
            'type' => 'wiki',
            'module' => 'budget',
            'contenu' => "## Section test\n\nContenu markdown.",
            'tags' => 'budget, test',
        ])->assertRedirect();

        $item = Connaissance::where('titre', 'Guide test budget')->first();
        $this->assertNotNull($item);
        $this->assertSame('brouillon', $item->statut);

        $this->actingAs($this->admin)->post(route('ged.connaissances.publier', $item))
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertSame('publie', $item->fresh()->statut);

        $this->actingAs($this->admin)->get(route('ged.connaissances.show', $item))
            ->assertOk()
            ->assertSee('Section test', false);
    }

    public function test_refuse_sans_droit_ged(): void
    {
        $user = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $this->actingAs($user)->get(route('ged.connaissances.index'))->assertForbidden();
    }
}
