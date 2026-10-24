<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\MfaService;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SpaSessionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InteroperabiliteApiPermissionsSeeder::class]);
    }

    private function spa(array $headers = []): array
    {
        return array_merge([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ], $headers);
    }

    private function user(string $role = 'administrateur'): User
    {
        return User::factory()->create([
            'password' => Hash::make('secret-123'),
            'actif' => true,
            'statut' => 'actif',
        ])->assignRole($role);
    }

    public function test_session_login_etablit_une_session(): void
    {
        $user = $this->user();

        $this->withHeaders($this->spa())
            ->postJson(route('api.session.login'), [
                'email' => $user->email,
                'password' => 'secret-123',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.mode_auth', 'session');

        $this->withHeaders($this->spa())
            ->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_session_login_refuse_un_mot_de_passe_invalide(): void
    {
        $user = $this->user();

        $this->withHeaders($this->spa())
            ->postJson(route('api.session.login'), [
                'email' => $user->email,
                'password' => 'mauvais',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_session_peut_lire_exercices_sans_jeton(): void
    {
        $user = $this->user();

        $this->withHeaders($this->spa())
            ->postJson(route('api.session.login'), [
                'email' => $user->email,
                'password' => 'secret-123',
            ])
            ->assertOk();

        $this->withHeaders($this->spa())
            ->getJson(route('api.exercices'))
            ->assertOk();
    }

    public function test_tableau_bord_et_navigation_session(): void
    {
        $user = $this->user();

        $this->actingAs($user);

        $this->getJson(route('api.tableau-bord'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['kpis', 'budget', 'pap', 'persona']]);

        $this->getJson(route('api.navigation'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['items', 'legacy_url']]);
    }

    public function test_cookie_csrf_sanctum_est_disponible(): void
    {
        $this->withHeaders($this->spa())
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();
    }

    public function test_tableau_bord_refuse_sans_authentification(): void
    {
        $this->getJson(route('api.tableau-bord'))->assertUnauthorized();
    }

    public function test_scope_tompro_ne_passe_pas_lecture_via_jeton(): void
    {
        $user = $this->user();
        \Laravel\Sanctum\Sanctum::actingAs($user, ['tompro']);

        $this->getJson(route('api.tableau-bord'))->assertForbidden();
    }

    public function test_session_logout_invalide_la_session(): void
    {
        $user = $this->user('gestionnaire_budgetaire');

        $this->withHeaders($this->spa())
            ->postJson(route('api.session.login'), [
                'email' => $user->email,
                'password' => 'secret-123',
            ])
            ->assertOk();

        $this->withHeaders($this->spa())
            ->getJson(route('api.me'))
            ->assertOk();

        $this->withHeaders($this->spa())
            ->postJson(route('api.session.logout'))
            ->assertOk()
            ->assertJsonPath('code', 'logged_out');

        $this->app['auth']->forgetGuards();

        $this->withHeaders($this->spa())
            ->getJson(route('api.me'))
            ->assertUnauthorized();
    }

    public function test_session_mfa_totp_complete_la_connexion(): void
    {
        config(['mfa.enabled' => true, 'mfa.demo_code' => '123456']);
        $user = $this->user('gestionnaire_budgetaire');
        $secret = app(Google2FA::class)->generateSecretKey();
        app(MfaService::class)->activer($user, $secret, ['ABCD-EFGH']);

        $this->withHeaders($this->spa())
            ->postJson(route('api.session.login'), [
                'email' => $user->email,
                'password' => 'secret-123',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'mfa_required');

        $this->assertGuest();

        $this->withHeaders($this->spa())
            ->postJson(route('api.session.mfa'), ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('user.email', $user->email);
    }
}
