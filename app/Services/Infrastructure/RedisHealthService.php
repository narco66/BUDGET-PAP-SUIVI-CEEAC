<?php

namespace App\Services\Infrastructure;

use Illuminate\Support\Facades\Redis;
use Throwable;

/** Vérification de disponibilité Redis (P2-2). */
class RedisHealthService
{
    /** @return array{ok: bool, latency_ms: ?float, error: ?string, connection: string} */
    public function verifier(): array
    {
        $connection = config('cache.default') === 'redis'
            ? (string) config('cache.stores.redis.connection', 'cache')
            : 'default';

        try {
            $debut = microtime(true);
            Redis::connection($connection)->ping();
            $latency = round((microtime(true) - $debut) * 1000, 2);

            return [
                'ok' => true,
                'latency_ms' => $latency,
                'error' => null,
                'connection' => $connection,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'latency_ms' => null,
                'error' => $e->getMessage(),
                'connection' => $connection,
            ];
        }
    }

    public function estUtilise(): bool
    {
        $queue = config('queue.default');

        return config('cache.default') === 'redis'
            || in_array(config('queue.default'), ['redis', 'redis_failover'], true);
    }
}
