<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    public $timestamps = false;

    protected $table = 'login_logs';

    protected $fillable = ['user_id', 'email', 'succes', 'motif', 'ip', 'user_agent', 'created_at'];

    protected $casts = ['succes' => 'boolean', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
