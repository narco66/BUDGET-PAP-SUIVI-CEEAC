<?php

namespace Tests\Feature;

use App\Jobs\GenererRapportJob;
use App\Services\AdministrationTechnique\SanteService;
use App\Services\Infrastructure\RedisHealthService;
use App\Support\QueueNames;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2-2 — Redis queue/cache production. */
class PerformanceRedisTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_names_priorites_worker(): void
    {
        $priorities = QueueNames::workerPriorities();

        $this->assertSame('reporting', $priorities[0]);
        $this->assertContains('default', $priorities);
    }

    public function test_rapport_job_utilise_la_file_reporting(): void
    {
        $job = new GenererRapportJob('pap', 1, 'pdf', [], 1);

        $this->assertSame(QueueNames::reporting(), $job->queue);
    }

    public function test_sante_inclut_controles_redis_et_cache(): void
    {
        $cles = array_column(app(SanteService::class)->controles(), 'cle');

        $this->assertContains('redis', $cles);
        $this->assertContains('cache', $cles);
    }

    public function test_redis_health_retourne_structure(): void
    {
        $result = app(RedisHealthService::class)->verifier();

        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('connection', $result);
    }

    public function test_cache_warning_en_production_si_database(): void
    {
        config([
            'cache.default' => 'database',
            'app.env' => 'production',
            'performance.redis.warn_database_in_production' => true,
        ]);

        $cache = collect(app(SanteService::class)->controles())->firstWhere('cle', 'cache');
        $this->assertSame('warning', $cache['statut']);
    }
}
