<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationType extends Model
{
    protected $fillable = [
        'code', 'module', 'libelle', 'description',
        'criticite_defaut', 'canal_defaut', 'actif',
    ];

    protected $casts = ['actif' => 'boolean'];

    public function templates(): HasMany
    {
        return $this->hasMany(NotificationTemplate::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
