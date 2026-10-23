<?php

namespace App\Notifications\Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Notification in-app transverse workflow (corbeille, relances P1). */
class WorkflowCorbeilleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $titre,
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly ?string $workflowCode = null,
        public readonly ?int $instanceId = null,
    ) {
    }

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'titre' => $this->titre,
            'message' => $this->message,
            'url' => $this->url,
            'workflow_code' => $this->workflowCode,
            'instance_id' => $this->instanceId,
        ];
    }
}
