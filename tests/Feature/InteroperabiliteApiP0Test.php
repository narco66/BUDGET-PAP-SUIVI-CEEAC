<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\User;
use App\Support\ApiJournalHelper;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Phase I-P0 — permissions API, rate limiting, journal enrichi. */
class InteroperabiliteApiP0Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InteroperabiliteApiPermissionsSeeder::class]);
    }

    private function apiUser(string $role = 'controleur_financier'): User
    {
        return User::factory()->create([
            'password' => Hash::make('secret-123'),
            'actif' => true,
        ])->assignRole($role);
    }

    public function test_migration_p0_colonnes_api_logs(): void
    {
        foreach (['connected_app_id', 'request_hash', 'user_agent', 'statut_metier'] as $col) {
            $this->assertTrue(Schema::hasColumn('api_logs', $col));
        }
    }

    public function test_permissions_api_seedees(): void
    {
        foreach (['api.view', 'api.create', 'api.update', 'api.publish', 'api.monitor', 'api.admin'] as $perm) {
            $this->assertNotNull(Permission::findByName($perm, 'web'));
        }

        $this->assertTrue($this->apiUser()->can('api.create'));
        $this->assertTrue(User::factory()->create(['actif' => true])->assignRole('directeur')->can('api.view'));
        $this->assertFalse(User::factory()->create(['actif' => true])->assignRole('point_focal')->can('api.create'));
    }

    public function test_token_exige_permission_api_create(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-123'),
            'actif' => true,
        ])->assignRole('point_focal');

        $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'secret-123',
        ])->assertStatus(422);
    }

    public function test_rate_limit_token(): void
    {
        config(['interoperabilite.rate_limit.token_per_minute' => 3]);
        $user = $this->apiUser();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('api.token'), [
                'email' => $user->email,
                'password' => 'secret-123',
            ])->assertCreated();
        }

        $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'secret-123',
        ])->assertStatus(429);

        $this->assertDatabaseHas('api_logs', [
            'statut' => 429,
            'statut_metier' => 'rejet',
        ]);
    }

    public function test_journal_enrichi_empreinte_et_user_agent(): void
    {
        $user = $this->apiUser();
        Sanctum::actingAs($user, ['lecture']);

        $this->withHeader('User-Agent', 'CEEAC-TestClient/1.0')
            ->getJson(route('api.exercices'))
            ->assertOk();

        $log = ApiLog::latest('id')->first();
        $this->assertNotNull($log->request_hash);
        $this->assertSame(64, strlen($log->request_hash));
        $this->assertSame('CEEAC-TestClient/1.0', $log->user_agent);
        $this->assertSame('succes', $log->statut_metier);
        $this->assertSame(200, $log->statut);
    }

    public function test_hash_exclut_mot_de_passe(): void
    {
        $request = Request::create('/api/v1/token', 'POST', [
            'email' => 'a@test.com',
            'password' => 'secret',
            'abilities' => ['lecture'],
        ]);

        $hash1 = ApiJournalHelper::hashRequete($request);
        $request2 = Request::create('/api/v1/token', 'POST', [
            'email' => 'a@test.com',
            'password' => 'autre',
            'abilities' => ['lecture'],
        ]);

        $this->assertSame($hash1, ApiJournalHelper::hashRequete($request2));
    }

    public function test_seeder_interoperabilite(): void
    {
        $this->seed(\Database\Seeders\InteroperabiliteApiSeeder::class);
        $this->assertNotNull(Permission::findByName('api.admin', 'web'));
    }
}
