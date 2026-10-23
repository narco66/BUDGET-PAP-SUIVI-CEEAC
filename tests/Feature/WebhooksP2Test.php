<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookEvent;
use App\Models\WebhookLivraison;
use App\Services\WebhookService;
use Database\Seeders\InteroperabiliteApiPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WebhookEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase I-P2 — webhooks async, retry, catalogue, UI MOA. */
class WebhooksP2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InteroperabiliteApiPermissionsSeeder::class, WebhookEventsSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_migration_p2_tables_et_colonnes(): void
    {
        $this->assertTrue(Schema::hasTable('webhook_events'));
        foreach (['statut', 'tentatives', 'prochaine_tentative_le', 'erreur'] as $col) {
            $this->assertTrue(Schema::hasColumn('webhook_livraisons', $col));
        }
        foreach (['connected_app_id', 'methode'] as $col) {
            $this->assertTrue(Schema::hasColumn('webhooks', $col));
        }
    }

    public function test_catalogue_evenements_seed(): void
    {
        $this->assertGreaterThanOrEqual(9, WebhookEvent::count());
        $this->assertNotNull(WebhookEvent::where('code', 'reporting.publie')->first());
    }

    public function test_livraison_async_succes(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $wh = Webhook::create([
            'libelle' => 'Hook', 'url' => 'https://partenaire.test/hook',
            'evenement' => 'engagement.vise', 'secret' => 'secret', 'actif' => true,
        ]);

        app(WebhookService::class)->declencher('engagement.vise', ['numero' => 'ENG-1']);

        Http::assertSent(fn ($r) => $r->url() === 'https://partenaire.test/hook' && $r->hasHeader('X-Signature'));
        $this->assertDatabaseHas('webhook_livraisons', [
            'webhook_id' => $wh->id,
            'statut' => 'livre',
            'succes' => true,
        ]);
    }

    public function test_echec_planifie_retry(): void
    {
        config(['interoperabilite.webhooks.max_tentatives' => 3]);
        Http::fake(['*' => Http::response('fail', 500)]);

        $wh = Webhook::create([
            'libelle' => 'Hook', 'url' => 'https://partenaire.test/hook',
            'evenement' => 'paiement.valide', 'secret' => 's', 'actif' => true,
        ]);

        app(WebhookService::class)->declencher('paiement.valide', ['numero' => 'PAY-1']);

        $liv = WebhookLivraison::where('webhook_id', $wh->id)->first();
        $this->assertSame('en_attente', $liv->statut);
        $this->assertSame(1, $liv->tentatives);
        $this->assertNotNull($liv->prochaine_tentative_le);
    }

    public function test_commande_relivrer(): void
    {
        config(['interoperabilite.webhooks.max_tentatives' => 5]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $wh = Webhook::create([
            'libelle' => 'Hook', 'url' => 'https://partenaire.test/h2',
            'evenement' => 'budget.adopte', 'secret' => 's', 'actif' => true,
        ]);

        $liv = $wh->livraisons()->create([
            'evenement' => 'budget.adopte',
            'payload' => ['evenement' => 'budget.adopte', 'donnees' => [], 'horodatage' => now()->toIso8601String()],
            'statut' => 'en_attente',
            'tentatives' => 1,
            'prochaine_tentative_le' => now()->subMinute(),
            'succes' => false,
            'created_at' => now(),
        ]);

        Artisan::call('webhooks:relivrer');

        $this->assertSame('livre', $liv->fresh()->statut);
    }

    public function test_webhook_inactif_non_declenche(): void
    {
        Http::fake();
        Webhook::create([
            'libelle' => 'Off', 'url' => 'https://x.test/h',
            'evenement' => 'engagement.vise', 'actif' => false,
        ]);

        app(WebhookService::class)->declencher('engagement.vise', []);

        Http::assertNothingSent();
        $this->assertDatabaseCount('webhook_livraisons', 0);
    }

    public function test_admin_crud_webhook(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.interoperabilite.webhooks.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.interoperabilite.webhooks.create'))->assertOk();

        $this->actingAs($admin)->post(route('admin.interoperabilite.webhooks.store'), [
            'libelle' => 'Partenaire recettes',
            'url' => 'https://partner.test/recettes',
            'methode' => 'POST',
            'evenement' => 'recette.encaissement_valide',
            'actif' => 1,
        ])->assertRedirect(route('admin.interoperabilite.webhooks.index'));

        $this->assertDatabaseHas('webhooks', ['evenement' => 'recette.encaissement_valide']);
    }

    public function test_en_ligne_admin(): void
    {
        Http::fake(['*' => Http::response(['pong' => true], 200)]);
        $admin = $this->admin();
        $wh = Webhook::create([
            'libelle' => 'Test', 'url' => 'https://partner.test/ping',
            'evenement' => 'pap.publie', 'secret' => 'abc', 'actif' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.interoperabilite.webhooks.tester', $wh))
            ->assertRedirect();

        $this->assertDatabaseHas('webhook_livraisons', [
            'webhook_id' => $wh->id,
            'statut' => 'livre',
        ]);
    }

    public function test_signature_hmac(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        Webhook::create([
            'libelle' => 'Sig', 'url' => 'https://partner.test/s',
            'evenement' => 'reporting.publie', 'secret' => 'cle-test', 'actif' => true,
        ]);

        app(WebhookService::class)->declencher('reporting.publie', ['ref' => 'R-1']);

        Http::assertSent(function ($request) {
            $payload = $request->data();
            $sigAttendue = hash_hmac('sha256', json_encode($payload), 'cle-test');

            return $request->header('X-Signature')[0] === $sigAttendue;
        });
    }
}
