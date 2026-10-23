<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Notification in-app institutionnelle (canal database enrichi N-P0). */
class InstitutionInAppNotification extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $meta */
    public function __construct(
        public readonly string $typeCode,
        public readonly string $module,
        public readonly string $criticite,
        public readonly string $titre,
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly array $meta = [],
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->typeCode,
            'type_code' => $this->typeCode,
            'module' => $this->module,
            'criticite' => $this->criticite,
            'titre' => $this->titre,
            'message' => $this->message,
            'url' => $this->url,
            'meta' => $this->meta,
        ];
    }
}
