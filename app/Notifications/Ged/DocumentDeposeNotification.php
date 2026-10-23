<?php

namespace App\Notifications\Ged;

use App\Models\Ged\GedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentDeposeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly GedDocument $document) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (config('ged.notifications.mail', false) && $notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('GED — Nouveau document déposé')
            ->line("Document : {$this->document->titre}")
            ->line("Référence : {$this->document->reference}")
            ->action('Consulter', route('ged.documents.show', $this->document));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Document déposé',
            'message' => "« {$this->document->titre} » a été déposé ({$this->document->reference}).",
            'document_id' => $this->document->id,
            'reference' => $this->document->reference,
            'url' => route('ged.documents.show', $this->document),
        ];
    }
}
