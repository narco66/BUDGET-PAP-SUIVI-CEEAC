<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Webhook;
use App\Services\WebhookService;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InteroperabiliteApiPermissionsSeeder::class]);
    }

    private function user(string $role = 'controleur_financier'): User
    {
        return User::factory()->create([
            'password' => Hash::make('secret-123'),
            'actif' => true,
        ])->assignRole($role);
    }

    public function test_emission_de_jeton(): void
    {
        $user = $this->user();

        $this->postJson(route('api.token'), [
            'email' => $user->email, 'password' => 'secret-123', 'abilities' => ['lecture'],
        ])->assertCreated()->assertJsonStructure(['token', 'type', 'abilities']);
    }

    public function test_jeton_refuse_si_identifiants_invalides(): void
    {
        $user = $this->user();
        $this->postJson(route('api.token'), ['email' => $user->email, 'password' => 'mauvais'])
            ->assertStatus(422);
    }

    public function test_endpoint_protege_exige_authentification(): void
    {
        $this->getJson(route('api.exercices'))->assertUnauthorized();
    }

    public function test_acces_avec_scope_lecture(): void
    {
        Sanctum::actingAs($this->user(), ['lecture']);
        $this->getJson(route('api.exercices'))->assertOk();
    }

    public function test_scope_insuffisant_est_refuse(): void
    {
        Sanctum::actingAs($this->user(), ['tompro']);
        $this->getJson(route('api.exercices'))->assertForbidden();
    }

    public function test_tompro_exige_le_scope_tompro(): void
    {
        Sanctum::actingAs($this->user(), ['lecture']);
        $this->getJson(route('api.tompro.paiements'))->assertForbidden();

        Sanctum::actingAs($this->user(), ['tompro']);
        $this->getJson(route('api.tompro.paiements'))
            ->assertOk()
            ->assertJsonStructure(['source', 'cible', 'nombre', 'ecritures']);
    }

    public function test_appels_api_sont_journalises(): void
    {
        Sanctum::actingAs($this->user(), ['lecture']);
        $this->getJson(route('api.exercices'))->assertOk();

        $this->assertDatabaseHas('api_logs', [
            'chemin' => 'api/v1/exercices',
            'methode' => 'GET',
            'statut' => 200,
            'statut_metier' => 'succes',
        ]);

        $this->assertNotNull(\App\Models\ApiLog::first()->request_hash);
    }

    public function test_declenchement_webhook(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $wh = Webhook::create(['libelle' => 'Test', 'url' => 'https://partenaire.test/hook', 'evenement' => 'engagement.vise', 'secret' => 's', 'actif' => true]);

        app(WebhookService::class)->declencher('engagement.vise', ['numero' => 'ENG-2026-0001']);

        Http::assertSent(fn ($r) => $r->url() === 'https://partenaire.test/hook' && $r->hasHeader('X-Signature'));
        $this->assertDatabaseHas('webhook_livraisons', ['webhook_id' => $wh->id, 'evenement' => 'engagement.vise', 'succes' => true]);
    }

    public function test_webhook_inactif_non_declenche(): void
    {
        Http::fake();
        Webhook::create(['libelle' => 'Off', 'url' => 'https://x.test/h', 'evenement' => 'engagement.vise', 'actif' => false]);

        app(WebhookService::class)->declencher('engagement.vise', []);

        Http::assertNothingSent();
    }
}
