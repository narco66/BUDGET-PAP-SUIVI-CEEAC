<?php

namespace App\Notifications\Ged;

use App\Models\Ged\GedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DocumentPartageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly GedDocument $document, public readonly string $partagePar) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Document partagé',
            'message' => "{$this->partagePar} a partagé « {$this->document->titre} » avec vous.",
            'document_id' => $this->document->id,
            'url' => route('ged.documents.show', $this->document),
        ];
    }
}
