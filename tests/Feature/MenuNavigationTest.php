<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Navigation\MenuService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2-7 — menu sidebar centralisé. */
class MenuNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['mfa.enabled' => false]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_administrateur_voit_le_tableau_de_bord_et_la_ged(): void
    {
        $items = app(MenuService::class)->build($this->user('administrateur'));

        $labels = collect($items)->pluck('label');
        $this->assertTrue($labels->contains('Tableau de bord'));
        $this->assertTrue($labels->contains('Gestion documentaire (GED)'));
        $this->assertTrue($labels->contains('Documents'));
    }

    public function test_lecteur_ne_voit_pas_les_ecrans_administration(): void
    {
        $labels = collect(app(MenuService::class)->build($this->user('lecteur')))->pluck('label');

        $this->assertFalse($labels->contains('Circuits workflow'));
        $this->assertFalse($labels->contains('Paramètres GED'));
        $this->assertFalse($labels->contains('Modèles e-mail'));
    }

    public function test_point_focal_a_un_perimetre_restreint(): void
    {
        $admin = app(MenuService::class)->build($this->user('administrateur'));
        $focal = app(MenuService::class)->build($this->user('point_focal'));

        $this->assertGreaterThan(count($focal), count($admin));
        $this->assertFalse(collect($focal)->pluck('label')->contains('Budget & Crédits'));
    }

    public function test_sections_sans_liens_visibles_sont_masquees(): void
    {
        $items = app(MenuService::class)->build($this->user('comptable'));
        $types = collect($items)->pluck('type');

        foreach ($types as $i => $type) {
            if ($type === 'section') {
                $this->assertSame('link', $types[$i + 1] ?? null, 'Section sans lien suivant');
            }
        }
    }

    public function test_layout_rend_le_menu_configurable(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tableau de bord');
    }

    public function test_toutes_les_routes_du_menu_existent(): void
    {
        foreach (config('menu.items', []) as $entry) {
            if (($entry['type'] ?? 'link') !== 'link') {
                continue;
            }
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($entry['route']),
                "Route menu introuvable : {$entry['route']}"
            );
        }
    }
}
