<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalAudit extends Model
{
    public $timestamps = false; // seul created_at, géré par la base

    protected $table = 'journaux_audit';

    protected $fillable = [
        'user_id', 'user_nom', 'role', 'auditable_type', 'auditable_id',
        'action', 'avant', 'apres', 'ip', 'url', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'avant' => 'array',
        'apres' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
