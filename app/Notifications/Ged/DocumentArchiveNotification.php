<?php

namespace App\Notifications\Ged;

use App\Models\Ged\GedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DocumentArchiveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly GedDocument $document, public readonly string $motif) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Document archivé',
            'message' => "« {$this->document->titre} » a été archivé. Motif : {$this->motif}",
            'document_id' => $this->document->id,
            'url' => route('ged.archives'),
        ];
    }
}
