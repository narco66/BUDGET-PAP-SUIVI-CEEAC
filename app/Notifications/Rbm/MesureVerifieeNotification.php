<?php

namespace App\Notifications\Rbm;

use App\Models\Mesure;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Notification : une mesure IOV est prête à être validée. */
class MesureVerifieeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Mesure $mesure)
    {
    }

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        $ind = $this->mesure->indicateur;

        return [
            'titre' => 'Mesure IOV à valider',
            'message' => "La mesure {$this->mesure->periode} de l'indicateur {$ind->code} attend validation.",
            'indicateur_code' => $ind->code,
            'indicateur_id' => $ind->id,
            'mesure_id' => $this->mesure->id,
            'periode' => $this->mesure->periode,
            'url' => $ind->urlMesures() ?? route('notifications.index'),
        ];
    }
}
