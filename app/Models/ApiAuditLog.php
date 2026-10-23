<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'cible_type', 'cible_id', 'details', 'ip', 'created_at'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
