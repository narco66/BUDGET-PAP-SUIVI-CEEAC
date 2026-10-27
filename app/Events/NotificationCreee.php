<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Diffusion temps réel d'une notification sur le canal privé de son seul destinataire (§ 12, § 15).
 * Charge minimale : la notification complète reste lue depuis PostgreSQL (RN-12, RN-13).
 */
class NotificationCreee implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param array<string, mixed> $apercu */
    public function __construct(public readonly int $userId, public readonly array $apercu) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'notification.creee';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->apercu;
    }
}
