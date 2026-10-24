<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MfaSetupSpaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['mfa.enabled' => true, 'mfa.demo_code' => '123456']);
    }

    private function spa(): array
    {
        return [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ];
    }

    public function test_activation_et_desactivation_mfa_session(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-123'),
            'actif' => true,
            'statut' => 'actif',
        ])->assignRole('chef_service');

        $this->actingAs($user)
            ->withHeaders($this->spa())
            ->getJson(route('api.session.mfa.setup'))
            ->assertOk()
            ->assertJsonPath('actif', false)
            ->assertJsonStructure(['qr', 'secret']);

        $this->actingAs($user)
            ->withHeaders($this->spa())
            ->postJson(route('api.session.mfa.setup.confirm'), ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('actif', true)
            ->assertJsonStructure(['codes_secours']);

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->actingAs($user)
            ->withHeaders($this->spa())
            ->deleteJson(route('api.session.mfa.disable'))
            ->assertOk()
            ->assertJsonPath('actif', false);
    }

    public function test_admin_ne_peut_pas_desactiver_une_mfa_obligatoire(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('secret-123'),
            'actif' => true,
            'statut' => 'actif',
        ])->assignRole('administrateur');

        $this->actingAs($admin)->withHeaders($this->spa())->getJson(route('api.session.mfa.setup'))->assertOk();
        $this->actingAs($admin)
            ->withHeaders($this->spa())
            ->postJson(route('api.session.mfa.setup.confirm'), ['code' => '123456'])
            ->assertOk();

        $this->actingAs($admin)
            ->withHeaders($this->spa())
            ->deleteJson(route('api.session.mfa.disable'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');
    }
}
