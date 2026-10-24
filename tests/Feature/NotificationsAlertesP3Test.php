<?php



namespace Tests\Feature;



use App\Models\Alerte;

use App\Models\AuditNotification;

use App\Models\Exercice;

use App\Models\SmsLog;

use App\Models\User;

use App\Services\Alertes\AlerteEscaladeService;

use App\Services\Alertes\AlerteRegistryService;

use App\Services\Notifications\NotificationDispatcher;

use App\Services\Notifications\NotificationPreferenceService;

use App\Support\NotificationCriticite;

use Database\Seeders\NotificationsAlertesSeeder;

use Database\Seeders\RoleSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Notification;

use Illuminate\Support\Facades\Schema;

use Laravel\Sanctum\Sanctum;

use Tests\TestCase;



/** Phase N-P3 — SMS, escalades, préférences, audit, API REST. */

class NotificationsAlertesP3Test extends TestCase

{

    use RefreshDatabase;



    protected function setUp(): void

    {

        parent::setUp();

        config(['mfa.enabled' => false, 'notifications.email.queue' => false, 'notifications.sms.provider' => 'log']);

        $this->seed([RoleSeeder::class, NotificationsAlertesSeeder::class]);

    }



    private function user(string $role, array $extra = []): User

    {

        return User::factory()->create(array_merge(['actif' => true], $extra))->assignRole($role);

    }



    public function test_tables_p3_existent(): void

    {

        foreach (['sms_templates', 'sms_logs', 'notification_channels', 'audit_notifications'] as $table) {

            $this->assertTrue(Schema::hasTable($table));

        }

        $this->assertTrue(Schema::hasColumn('users', 'preferences_notifications'));

        $this->assertTrue(Schema::hasColumn('alertes', 'escalade_niveau'));

    }



    public function test_sms_envoye_via_dispatcher(): void

    {

        Mail::fake();



        $user = $this->user('commissaire', [

            'telephone' => '+24106000000',

            'preferences_notifications' => [

                'actif' => true,

                'canaux' => ['database' => true, 'email' => false, 'sms' => true],

                'sms_critique_uniquement' => false,

            ],

        ]);



        app(NotificationDispatcher::class)->envoyer(

            $user,

            'alerte.institutionnelle',

            'Alerte critique test',

            'Message SMS test',

            null,

            'systeme',

            NotificationCriticite::CRITIQUE,

            canaux: ['database', 'sms'],

        );



        $this->assertSame(1, SmsLog::where('destinataire_telephone', '+24106000000')->count());

        $this->assertDatabaseHas('audit_notifications', ['action' => 'envoi_sms']);

    }



    public function test_preferences_desactivent_canal(): void

    {

        $user = $this->user('directeur', [

            'preferences_notifications' => [

                'actif' => true,

                'canaux' => ['database' => true, 'email' => false, 'sms' => false],

            ],

        ]);



        $canaux = app(NotificationPreferenceService::class)

            ->canauxEffectifs($user, 'alerte.institutionnelle', NotificationCriticite::INFORMATION, ['database', 'email']);



        $this->assertSame(['database'], $canaux);

    }



    public function test_escalade_alerte_critique(): void

    {

        Notification::fake();

        Mail::fake();



        $this->user('directeur');

        $this->user('chef_service');



        $alerte = app(AlerteRegistryService::class)->enregistrer(

            'budget',

            'test_escalade',

            'Alerte critique budget',

            'Test escalade P3',

            NotificationCriticite::CRITIQUE,

            exerciceId: Exercice::create(['annee' => 2031, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,

            notifier: false,

        );



        $count = app(AlerteEscaladeService::class)->escalader($alerte, 1);



        $this->assertGreaterThanOrEqual(1, $count);

        $this->assertSame(1, $alerte->fresh()->escalade_niveau);

        $this->assertDatabaseHas('audit_notifications', ['action' => 'escalade', 'escalade_niveau' => 1]);

    }



    public function test_profil_preferences_http(): void

    {

        $user = $this->user('controleur_financier');



        $this->actingAs($user)

            ->get(route('profil.notifications.edit'))

            ->assertOk()

            ->assertSee('Préférences de notifications');



        $this->actingAs($user)

            ->put(route('profil.notifications.update'), [

                'actif' => 1,

                'database' => 1,

                'email' => 1,

                'sms' => 1,

                'sms_critique_uniquement' => 1,

            ])

            ->assertRedirect();



        $this->assertTrue($user->fresh()->preferences_notifications['canaux']['sms']);

        $this->assertDatabaseHas('audit_notifications', ['action' => 'preference_maj']);

    }



    public function test_api_notifications(): void

    {

        Notification::fake();

        Mail::fake();



        $user = $this->user('controleur_financier');

        app(NotificationDispatcher::class)->envoyer($user, 'systeme.information', 'Test API', 'Message API');



        Sanctum::actingAs($user, ['lecture']);



        $this->getJson('/api/v1/notifications/unread-count')

            ->assertOk()

            ->assertJsonStructure(['count']);



        $this->getJson('/api/v1/notifications?unread=1')

            ->assertOk();

    }



    public function test_api_resolve_alerte(): void

    {

        $cf = $this->user('controleur_financier');

        $alerte = Alerte::create([

            'reference' => 'ALT-API-01',

            'module' => 'budget',

            'code' => 'test',

            'criticite' => 'attention',

            'titre' => 'Test',

            'message' => 'Msg',

            'statut' => 'ouverte',

            'detecte_le' => now(),

        ]);



        Sanctum::actingAs($cf, ['lecture']);



        $this->postJson("/api/v1/notifications/alertes/{$alerte->id}/resolve", ['commentaire' => 'OK'])

            ->assertOk()

            ->assertJson(['ok' => true]);



        $this->assertSame('resolue', $alerte->fresh()->statut);

    }



    public function test_commande_escalader(): void

    {

        $this->artisan('alertes:escalader')->assertSuccessful();

    }

}

