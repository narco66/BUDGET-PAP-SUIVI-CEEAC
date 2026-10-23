<?php

namespace Tests\Feature;

use App\Models\JournalAudit;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2-4 — rétention et purge des journaux d'audit. */
class AuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_login_logs_au_dela_de_la_retention(): void
    {
        config(['audit.retention.login_logs' => 365]);

        $user = User::factory()->create(['actif' => true]);

        LoginLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'succes' => true,
            'ip' => '127.0.0.1',
            'created_at' => now()->subDays(400),
        ]);
        LoginLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'succes' => true,
            'ip' => '127.0.0.1',
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('audit:purge-journaux', ['--table' => 'login_logs'])
            ->assertSuccessful();

        $this->assertSame(1, LoginLog::count());
    }

    public function test_dry_run_ne_supprime_pas(): void
    {
        config(['audit.retention.journaux_audit' => 90]);

        $entree = JournalAudit::create([
            'action' => 'test_purge',
            'created_at' => now()->subDays(120),
        ]);

        $this->artisan('audit:purge-journaux', ['--table' => 'journaux_audit', '--dry-run' => true])
            ->assertSuccessful();

        $this->assertNotNull(JournalAudit::find($entree->id));
    }

    public function test_refuse_retention_inferieure_au_minimum(): void
    {
        config(['audit.purge.min_jours' => 90]);

        $this->artisan('audit:purge-journaux', ['--table' => 'login_logs', '--jours' => 30])
            ->assertFailed();
    }

    public function test_purge_desactivee_ne_fait_rien(): void
    {
        config(['audit.purge.enabled' => false]);

        LoginLog::create([
            'email' => 'old@ceeac.test',
            'succes' => false,
            'ip' => '127.0.0.1',
            'created_at' => now()->subYears(2),
        ]);

        $this->artisan('audit:purge-journaux', ['--table' => 'login_logs'])
            ->assertSuccessful();

        $this->assertSame(1, LoginLog::count());
    }
}
