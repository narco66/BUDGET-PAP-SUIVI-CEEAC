<?php

namespace App\Notifications\Ged;

use App\Models\Ged\GedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpirationProcheNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly GedDocument $document, public readonly int $joursRestants) {}

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
            ->subject('GED — Expiration proche')
            ->line("Le document « {$this->document->titre} » expire dans {$this->joursRestants} jour(s).")
            ->action('Consulter', route('ged.documents.show', $this->document));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Expiration proche',
            'message' => "« {$this->document->titre} » expire dans {$this->joursRestants} jour(s).",
            'document_id' => $this->document->id,
            'url' => route('ged.documents.show', $this->document),
        ];
    }
}
