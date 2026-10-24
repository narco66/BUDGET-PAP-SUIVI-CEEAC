<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\NotificationType;
use App\Models\Pap;
use App\Models\User;
use App\Notifications\InstitutionInAppNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workflow\WorkflowNotificationService;
use App\Services\WorkflowService;
use App\Support\NotificationCriticite;
use Database\Seeders\NotificationsAlertesPermissionsSeeder;
use Database\Seeders\NotificationsAlertesSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Phase N-P0 — permissions, référentiel, dispatcher, centre notifications. */
class NotificationsAlertesP0Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'notifications.email.queue' => false, 'notifications.sms.provider' => 'log']);
        $this->seed([
            RoleSeeder::class,
            NotificationsAlertesSeeder::class,
            WorkflowPermissionsSeeder::class,
            WorkflowSeeder::class,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_tables_p0_existent(): void
    {
        foreach (['notification_types', 'notification_templates', 'notification_logs'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasColumn('notifications', 'archive_at'));
    }

    public function test_permissions_notifications_alertes_seedees(): void
    {
        foreach (['notifications.view', 'notifications.send', 'alertes.view', 'alertes.resolve'] as $perm) {
            $this->assertNotNull(Permission::findByName($perm, 'web'));
        }

        $this->assertTrue($this->user('chef_service')->can('notifications.view'));
        $this->assertTrue($this->user('chef_service')->can('notifications.send'));
        $this->assertTrue($this->user('point_focal')->can('notifications.view'));
        $this->assertFalse($this->user('point_focal')->can('notifications.send'));
    }

    public function test_seeder_types_par_defaut(): void
    {
        $this->assertGreaterThanOrEqual(10, NotificationType::count());
        $this->assertDatabaseHas('notification_types', ['code' => 'workflow.etape_en_attente', 'module' => 'workflow']);
    }

    public function test_dispatcher_journalise_et_notifie(): void
    {
        Notification::fake();

        $dest = $this->user('controleur_financier');
        $emetteur = $this->user('administrateur');

        app(NotificationDispatcher::class)->envoyer(
            $dest,
            'workflow.etape_en_attente',
            'Test validation',
            'Message de test institutionnel.',
            '/dashboard',
            'workflow',
            NotificationCriticite::ATTENTION,
            emetteur: $emetteur,
        );

        Notification::assertSentTo($dest, InstitutionInAppNotification::class);
        $this->assertDatabaseHas('notification_logs', [
            'type_code' => 'workflow.etape_en_attente',
            'module' => 'workflow',
            'destinataire_id' => $dest->id,
            'statut' => 'envoye',
        ]);
    }

    public function test_centre_notifications_filtres_et_permissions(): void
    {
        $user = $this->user('directeur');
        app(NotificationDispatcher::class)->envoyer(
            $user,
            'pap.soumission',
            'PAP test',
            'Soumission PAP test',
            null,
            'pap',
        );

        $this->actingAs($user)
            ->get(route('notifications.index', ['module' => 'pap']))
            ->assertOk()
            ->assertSee('PAP test')
            ->assertSee('Centre de notifications');

        $sansDroit = User::factory()->create(['actif' => true]);
        $this->actingAs($sansDroit)
            ->get(route('notifications.index'))
            ->assertForbidden();
    }

    public function test_archivage_notification(): void
    {
        Mail::fake();
        $user = $this->user('commissaire');
        app(NotificationDispatcher::class)->envoyer(
            $user,
            'budget.adoption',
            'Budget adopté',
            'Le budget est adopté.',
        );

        $id = $user->fresh()->notifications->first()->id;

        $this->actingAs($user)
            ->post(route('notifications.archiver', $id))
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->notifications()->find($id)->archive_at);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Aucune notification pour ces critères');

        $this->actingAs($user)
            ->get(route('notifications.index', ['archive' => 1]))
            ->assertOk()
            ->assertSee('Budget adopté');
    }

    public function test_workflow_service_utilise_dispatcher(): void
    {
        Notification::fake();

        $pap = Pap::create([
            'exercice_id' => Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert'])->id,
            'reference' => 'PAP-NOTIF',
            'libelle' => 'PAP notif',
            'statut' => 'brouillon',
        ]);

        $instance = app(WorkflowService::class)->demarrer($pap, 'PAP', $this->user('directeur'));
        $cs = $this->user('chef_service');

        app(WorkflowNotificationService::class)->notifierEtapeCourante($instance->fresh());

        Notification::assertSentTo($cs, InstitutionInAppNotification::class);
    }

    public function test_refus_envoi_sans_permission(): void
    {
        $this->expectException(\RuntimeException::class);

        app(NotificationDispatcher::class)->envoyer(
            $this->user('point_focal'),
            'systeme.information',
            'Test',
            'Refus attendu',
            emetteur: $this->user('point_focal'),
        );
    }
}
