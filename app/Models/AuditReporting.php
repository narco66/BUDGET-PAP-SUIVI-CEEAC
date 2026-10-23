<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditReporting extends Model
{
    public $timestamps = false;

    protected $table = 'audit_reporting';

    protected $fillable = [
        'rapport_id', 'user_id', 'action', 'type_rapport',
        'ip', 'user_agent', 'metadonnees', 'created_at',
    ];

    protected $casts = [
        'metadonnees' => 'array',
        'created_at' => 'datetime',
    ];

    public function rapport(): BelongsTo
    {
        return $this->belongsTo(Rapport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
