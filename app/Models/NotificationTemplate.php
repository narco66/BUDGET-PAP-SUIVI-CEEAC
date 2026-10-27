<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'notification_type_id', 'code', 'canal', 'sujet', 'corps', 'actif',
    ];

    protected $casts = ['actif' => 'boolean'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }

    /** @param array<string, scalar|null> $variables */
    /** Remplace les variables {{cle}} dans le corps du modèle, ou dans le gabarit fourni (ex. le sujet). */
    public function appliquer(array $variables, ?string $gabarit = null): string
    {
        $corps = $gabarit ?? $this->corps;
        foreach ($variables as $cle => $valeur) {
            $corps = str_replace('{{'.$cle.'}}', (string) ($valeur ?? ''), $corps);
        }

        return $corps;
    }
}
