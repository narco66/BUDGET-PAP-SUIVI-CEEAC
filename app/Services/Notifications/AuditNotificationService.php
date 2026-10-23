<?php



namespace App\Services\Notifications;



use App\Models\AuditNotification;

use App\Models\User;



/** Journal d'audit MOA des événements notifications (N-P3). */

class AuditNotificationService

{

    /** @param  array<string, mixed>  $payload */

    public function journaliser(

        string $action,

        ?string $typeCode = null,

        ?string $module = null,

        ?string $canal = null,

        ?User $utilisateur = null,

        ?User $destinataire = null,

        ?int $notificationLogId = null,

        ?int $alerteId = null,

        ?int $escaladeNiveau = null,

        array $payload = [],

    ): AuditNotification {

        return AuditNotification::create([

            'action' => $action,

            'notification_log_id' => $notificationLogId,

            'alerte_id' => $alerteId,

            'type_code' => $typeCode,

            'module' => $module,

            'canal' => $canal,

            'utilisateur_id' => $utilisateur?->id,

            'destinataire_type' => $destinataire?->getMorphClass(),

            'destinataire_id' => $destinataire?->getKey(),

            'escalade_niveau' => $escaladeNiveau,

            'payload' => $payload ?: null,

            'ip' => request()?->ip(),

            'occurred_at' => now(),

        ]);

    }

}

