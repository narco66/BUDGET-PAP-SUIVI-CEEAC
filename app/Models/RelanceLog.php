<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RelanceLog extends Model
{
    protected $fillable = [
        'relance_rule_id', 'module', 'objet_type', 'objet_id',
        'destinataire_id', 'canal', 'statut', 'titre', 'message',
        'metadata', 'emis_le',
    ];

    protected $casts = [
        'metadata' => 'array',
        'emis_le' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RelanceRule::class, 'relance_rule_id');
    }

    public function objet(): MorphTo
    {
        return $this->morphTo();
    }

    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }
}
