<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\ApiLog;
use App\Models\ConnectedApp;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Interop\ConnectedAppService;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase I-P1 — registre applications connectées, clés, scopes, journal MOA. */
class ConnectedAppsP1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InteroperabiliteApiPermissionsSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    private function apiUser(): User
    {
        return User::factory()->create([
            'password' => Hash::make('secret-123'),
            'actif' => true,
        ])->assignRole('controleur_financier');
    }

    private function creerApp(array $overrides = []): ConnectedApp
    {
        return ConnectedApp::create(array_merge([
            'code' => 'APP_TEST',
            'libelle' => 'Application test',
            'type' => 'interne',
            'scopes_autorises' => ['lecture', 'suivi'],
            'statut' => 'actif',
        ], $overrides));
    }

    public function test_migration_p1_tables_et_colonnes(): void
    {
        $this->assertTrue(Schema::hasTable('connected_apps'));
        $this->assertTrue(Schema::hasTable('api_clients'));
        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'connected_app_id'));
        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'api_client_id'));
    }

    public function test_crud_application_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.interoperabilite.apps.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.interoperabilite.apps.create'))->assertOk();

        $this->actingAs($admin)->post(route('admin.interoperabilite.apps.store'), [
            'code' => 'PARTNER_X',
            'libelle' => 'Partenaire X',
            'type' => 'partenaire',
            'scopes_autorises' => ['lecture'],
            'statut' => 'actif',
        ])->assertRedirect(route('admin.interoperabilite.apps.index'));

        $this->assertDatabaseHas('connected_apps', ['code' => 'PARTNER_X']);
    }

    public function test_creation_client_et_revoquer(): void
    {
        $admin = $this->admin();
        $app = $this->creerApp();

        $resultat = app(ConnectedAppService::class)->creerClient($app, 'Prod');
        $this->assertNotEmpty($resultat['client_id']);
        $this->assertNotEmpty($resultat['client_secret']);

        $this->actingAs($admin)->post(route('admin.interoperabilite.apps.clients.revoquer', [$app, $resultat['client']]))
            ->assertRedirect();

        $this->assertSame('revoque', $resultat['client']->fresh()->statut);
    }

    public function test_token_avec_client_lie_application_et_intersecte_scopes(): void
    {
        $user = $this->apiUser();
        $app = $this->creerApp(['scopes_autorises' => ['lecture']]);
        $credentials = app(ConnectedAppService::class)->creerClient($app);

        $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'secret-123',
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'abilities' => ['lecture', 'tompro'],
        ])->assertCreated()
            ->assertJsonPath('abilities', ['lecture'])
            ->assertJsonPath('connected_app_id', $app->id);

        $token = PersonalAccessToken::latest('id')->first();
        $this->assertSame($app->id, $token->connected_app_id);
        $this->assertSame($credentials['client']->id, $token->api_client_id);
        $this->assertNotNull($token->expires_at);
    }

    public function test_token_expire_apres_duree_configuree(): void
    {
        $user = $this->apiUser();

        $response = $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'secret-123',
        ])->assertCreated()
            ->assertJsonStructure(['expires_at']);

        $plain = $response->json('token');
        [$tokenId] = explode('|', $plain, 2);

        $this->withToken($plain)->getJson(route('api.exercices'))->assertOk();

        PersonalAccessToken::findOrFail($tokenId)->update(['expires_at' => now()->subMinute()]);
        $this->app['auth']->forgetGuards();

        $this->withToken($plain)->getJson(route('api.exercices'))->assertUnauthorized();
    }

    public function test_scope_interdit_par_application_refuse(): void
    {
        $user = $this->apiUser();
        $app = $this->creerApp(['scopes_autorises' => ['lecture']]);
        $credentials = app(ConnectedAppService::class)->creerClient($app);

        $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'secret-123',
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'abilities' => ['tompro'],
        ])->assertStatus(422);
    }

    public function test_application_suspendue_bloque_api(): void
    {
        $user = $this->apiUser();
        $app = $this->creerApp();
        $credentials = app(ConnectedAppService::class)->creerClient($app);

        $response = $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'secret-123',
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
        ])->assertCreated();

        $plain = $response->json('token');
        $app->update(['statut' => 'suspendu']);

        $this->withToken($plain)->getJson(route('api.exercices'))->assertForbidden();
    }

    public function test_journal_admin_et_trace_connected_app_id(): void
    {
        $admin = $this->admin();
        $user = $this->apiUser();
        $app = $this->creerApp();
        $credentials = app(ConnectedAppService::class)->creerClient($app);

        $token = $user->createToken('test', ['lecture']);
        $token->accessToken->forceFill(['connected_app_id' => $app->id])->save();

        $this->withToken($token->plainTextToken)
            ->getJson(route('api.exercices'))
            ->assertOk();

        $this->assertDatabaseHas('api_logs', [
            'connected_app_id' => $app->id,
            'chemin' => 'api/v1/exercices',
        ]);

        $this->actingAs($admin)->get(route('admin.interoperabilite.journal', [
            'connected_app_id' => $app->id,
        ]))->assertOk()->assertSee('APP_TEST');
    }

    public function test_journal_refuse_sans_permission_monitor(): void
    {
        $user = User::factory()->create(['actif' => true])->assignRole('point_focal');

        $this->actingAs($user)->get(route('admin.interoperabilite.journal'))->assertForbidden();
    }

    public function test_seeder_interoperabilite_cree_tompro(): void
    {
        $this->seed(\Database\Seeders\InteroperabiliteApiSeeder::class);

        $this->assertDatabaseHas('connected_apps', ['code' => 'TOMPRO']);
        $this->assertTrue(ApiClient::whereHas('connectedApp', fn ($q) => $q->where('code', 'TOMPRO'))->exists());
    }
}
