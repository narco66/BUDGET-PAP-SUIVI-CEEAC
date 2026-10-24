<?php

namespace Tests\Feature;

use App\Models\Alerte;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Database\Seeders\NotificationsAlertesSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationsSpaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'notifications.email.queue' => false, 'notifications.sms.provider' => 'log']);
        $this->seed([RoleSeeder::class, NotificationsAlertesSeeder::class]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_sans_droit_est_refuse(): void
    {
        $this->actingAs(User::factory()->create(['actif' => true, 'statut' => 'actif']))
            ->getJson(route('api.notifications.hub'))
            ->assertForbidden();
    }

    public function test_hub_lecture_marquage_et_preferences(): void
    {
        Mail::fake();

        $user = $this->user('gestionnaire_budgetaire');
        app(NotificationDispatcher::class)->envoyer($user, 'systeme.information', 'Test SPA', 'Message SPA');

        $this->actingAs($user)
            ->getJson(route('api.notifications.hub'))
            ->assertOk()
            ->assertJsonPath('stats.non_lues', 1)
            ->assertJsonPath('peut_gerer', true)
            ->assertJsonPath('peut_resoudre', false);

        $liste = $this->actingAs($user)->getJson(route('api.notifications.index'))->assertOk();
        $id = data_get($liste->json(), 'data.data.0.id') ?? data_get($liste->json(), 'data.0.id');
        $this->assertNotEmpty($id);

        $this->actingAs($user)
            ->postJson(route('api.notifications.read', $id))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($user)
            ->getJson(route('api.notifications.unread'))
            ->assertOk()
            ->assertJsonPath('count', 0);

        app(NotificationDispatcher::class)->envoyer($user, 'systeme.information', 'Encore', 'Deuxième');
        $this->actingAs($user)
            ->postJson(route('api.notifications.tout-lu'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $seconde = $user->notifications()->latest()->firstOrFail();
        $this->actingAs($user)
            ->postJson(route('api.notifications.archiver', $seconde->id))
            ->assertOk()
            ->assertJsonPath('code', 'archive');

        $this->actingAs($user)
            ->putJson(route('api.notifications.preferences.update'), ['sms' => true, 'email' => true, 'database' => true, 'actif' => true])
            ->assertOk()
            ->assertJsonPath('data.sms', true);
    }

    public function test_resolution_alerte_refusee_sans_droit(): void
    {
        $alerte = Alerte::create([
            'reference' => 'ALT-SPA-01',
            'module' => 'budget',
            'code' => 'test',
            'criticite' => 'attention',
            'titre' => 'Test SPA',
            'message' => 'Msg',
            'statut' => 'ouverte',
            'detecte_le' => now(),
        ]);

        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->postJson(route('api.notifications.alertes.resolve', $alerte), ['commentaire' => 'non'])
            ->assertForbidden();

        $cf = $this->user('controleur_financier');
        $this->actingAs($cf)
            ->getJson(route('api.notifications.alertes'))
            ->assertOk()
            ->assertJsonFragment(['reference' => 'ALT-SPA-01']);

        $this->actingAs($cf)
            ->postJson(route('api.notifications.alertes.resolve', $alerte), ['commentaire' => 'OK SPA'])
            ->assertOk()
            ->assertJson(['ok' => true, 'statut' => 'resolue']);
    }
}
