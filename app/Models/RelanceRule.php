<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RelanceRule extends Model
{
    protected $fillable = [
        'code', 'module', 'libelle', 'description', 'intervalle_jours',
        'notification_type_code', 'roles_cibles', 'parametres', 'actif',
    ];

    protected $casts = [
        'roles_cibles' => 'array',
        'parametres' => 'array',
        'actif' => 'boolean',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(RelanceLog::class);
    }
}
