<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RbmHistorique extends Model
{
    protected $table = 'rbm_historiques';

    protected $fillable = ['historisable_type', 'historisable_id', 'action', 'donnees_avant', 'donnees_apres', 'user_id'];

    protected $casts = [
        'donnees_avant' => 'array',
        'donnees_apres' => 'array',
    ];

    public function historisable(): MorphTo
    {
        return $this->morphTo();
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
