<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Signal WebSocket — agrégats cockpit invalidés (P3-7). */
class CockpitMetricsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param  list<string>  $domains */
    public function __construct(
        public int $cacheVersion,
        public ?int $exerciceId = null,
        public array $domains = ['budget'],
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        $suffix = $this->exerciceId ?? 'all';

        return [new PrivateChannel('cockpit.'.$suffix)];
    }

    public function broadcastAs(): string
    {
        return 'metrics.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'cache_version' => $this->cacheVersion,
            'exercice_id' => $this->exerciceId,
            'domains' => $this->domains,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
