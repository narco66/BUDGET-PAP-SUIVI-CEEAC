<?php

namespace App\Notifications\Ged;

use App\Models\Ged\GedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AccesRefuseCritiqueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GedDocument $document,
        public readonly string $utilisateur,
        public readonly string $action,
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
            'titre' => 'Tentative d\'accès non autorisée',
            'message' => "{$this->utilisateur} — accès refusé ({$this->action}) sur « {$this->document->titre} ».",
            'document_id' => $this->document->id,
            'url' => route('ged.journal'),
        ];
    }
}
