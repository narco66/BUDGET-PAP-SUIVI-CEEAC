<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdministrationTechnique\QueueMonitorService;
use Database\Seeders\AdministrationTechniqueSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** P3-5 — Monitoring des files d'attente (alternative native à Horizon). */
class QueueMonitorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdministrationTechniqueSeeder::class);
        config(['mfa.enabled' => false]);

        $this->admin = User::create([
            'name' => 'DSI', 'email' => 'dsi@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_tableau_de_bord_accessible(): void
    {
        $this->actingAs($this->admin)->get(route('administration-technique.queues.index'))
            ->assertOk()
            ->assertSee('Monitoring des files d\'attente', false)
            ->assertSee('reporting', false);
    }

    public function test_service_agrege_les_files(): void
    {
        $monitor = app(QueueMonitorService::class);
        $resume = $monitor->resume();

        $this->assertArrayHasKey('driver', $resume);
        $this->assertArrayHasKey('total_en_attente', $resume);
        $this->assertArrayHasKey('commande_worker', $resume);
        $this->assertStringContainsString('queue:work', $resume['commande_worker']);

        $files = $monitor->filesParQueue();
        $this->assertArrayHasKey('reporting', $files);
        $this->assertArrayHasKey('default', $files);
    }

    public function test_relance_et_suppression_job_echoue(): void
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Tests\\FakeJob']),
            'exception' => 'Exception: test',
            'failed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('administration-technique.queues.retry', $uuid))
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Tests\\FakeJob']),
            'exception' => 'Exception: test',
            'failed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('administration-technique.queues.forget', $uuid))
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    public function test_refuse_sans_droit(): void
    {
        $lecteur = User::create([
            'name' => 'Lecteur', 'email' => 'lect.queue@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');

        $this->actingAs($lecteur)->get(route('administration-technique.queues.index'))->assertForbidden();
    }
}
