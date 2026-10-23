<?php

namespace App\Notifications\Ged;

use App\Models\Ged\GedDocument;
use App\Models\Ged\GedSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentSignatureDemandeeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GedDocument $document,
        public readonly GedSignature $signature,
    ) {}

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
            ->subject('GED — Signature demandée')
            ->line("Vous êtes invité à signer « {$this->document->titre} ».")
            ->action('Signer', route('ged.signatures.index'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Signature demandée',
            'message' => "Signature requise pour « {$this->document->titre} ».",
            'document_id' => $this->document->id,
            'signature_id' => $this->signature->id,
            'url' => route('ged.signatures.index'),
        ];
    }
}
