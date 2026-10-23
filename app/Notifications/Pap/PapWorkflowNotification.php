<?php

namespace App\Notifications\Pap;

use App\Models\Pap;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Notification in-app du circuit de validation PAP. */
class PapWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Pap $pap,
        public readonly string $type,
        public readonly string $titre,
        public readonly string $message,
        public readonly ?string $roleEtape = null,
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
            'pap_id' => $this->pap->id,
            'pap_reference' => $this->pap->reference,
            'exercice' => $this->pap->exercice?->annee,
            'statut' => $this->pap->statut,
            'role_etape' => $this->roleEtape,
            'url' => route('paps.show', $this->pap),
        ];
    }
}
