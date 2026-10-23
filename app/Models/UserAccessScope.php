<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessScope extends Model
{
    protected $table = 'user_access_scopes';

    protected $fillable = ['user_id', 'type', 'scope_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
