<?php

namespace App\Support;

/** Files d'attente nommées (config/performance.php). */
final class QueueNames
{
    public static function reporting(): string
    {
        return config('performance.queues.reporting', 'reporting');
    }

    public static function notifications(): string
    {
        return config('performance.queues.notifications', 'notifications');
    }

    public static function webhooks(): string
    {
        return config('performance.queues.webhooks', 'webhooks');
    }

    public static function default(): string
    {
        return config('performance.queues.default', 'default');
    }

    /** @return list<string> */
    public static function workerPriorities(): array
    {
        return [
            self::reporting(),
            self::notifications(),
            self::webhooks(),
            self::default(),
        ];
    }
}
