<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\MfaService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** P1-1 — authentification à deux facteurs TOTP. */
class MfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['mfa.enabled' => true]);
    }

    private function user(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make('secret-123'),
            'actif' => true,
            'statut' => 'actif',
        ], $overrides));
    }

    private function activerMfa(User $user, ?string $secret = null): string
    {
        $secret ??= app(Google2FA::class)->generateSecretKey();
        app(MfaService::class)->activer($user, $secret, ['ABCD-EFGH', 'IJKL-MNOP']);

        return $secret;
    }

    public function test_login_sans_mfa_accede_directement(): void
    {
        $user = $this->user(['email' => 'simple@ceeac.test']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_avec_mfa_redirige_vers_challenge(): void
    {
        $user = $this->user(['email' => 'mfa@ceeac.test']);
        $this->activerMfa($user);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-123'])
            ->assertRedirect(route('mfa.challenge'));

        $this->assertGuest();
    }

    public function test_challenge_totp_valide_complete_connexion(): void
    {
        $user = $this->user(['email' => 'totp@ceeac.test']);
        $secret = $this->activerMfa($user);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-123']);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post(route('mfa.challenge.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_challenge_accepte_code_demo(): void
    {
        config(['mfa.demo_code' => '123456']);

        $user = $this->user(['email' => 'demo-mfa@ceeac.test']);
        $this->activerMfa($user);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-123']);

        $this->post(route('mfa.challenge.verify'), ['code' => '123456'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_role_sensible_sans_mfa_redirige_setup(): void
    {
        $admin = $this->user(['email' => 'admin-mfa@ceeac.test'])->assignRole('administrateur');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('mfa.setup'));
    }

    public function test_activation_mfa_via_setup(): void
    {
        $user = $this->user(['email' => 'setup@ceeac.test']);
        $secret = app(MfaService::class)->genererSecret();

        $this->actingAs($user)->get(route('mfa.setup'))->assertOk();

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->actingAs($user)->withSession(['mfa.pending_secret' => $secret])
            ->post(route('mfa.setup.confirm'), ['code' => $code])
            ->assertRedirect(route('mfa.setup'));

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertTrue(app(MfaService::class)->aMfaActive($user));
    }

    public function test_activation_mfa_accepte_code_demo(): void
    {
        config(['mfa.demo_code' => '123456']);

        $user = $this->user(['email' => 'setup-demo@ceeac.test']);
        $secret = app(MfaService::class)->genererSecret();

        $this->actingAs($user)->withSession(['mfa.pending_secret' => $secret])
            ->post(route('mfa.setup.confirm'), ['code' => '123456'])
            ->assertRedirect(route('mfa.setup'));

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertTrue(app(MfaService::class)->aMfaActive($user));
    }
}
