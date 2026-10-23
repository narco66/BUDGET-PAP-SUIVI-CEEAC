<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'notification_type_id', 'type_code', 'module', 'criticite', 'canal',
        'destinataire_type', 'destinataire_id', 'emetteur_id',
        'titre', 'message', 'url', 'statut', 'metadata',
        'laravel_notification_id', 'envoye_le',
    ];

    protected $casts = [
        'metadata' => 'array',
        'envoye_le' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }

    public function emetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emetteur_id');
    }
}
