<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Adresse IP verrouillée (anti brute-force) — automatique ou manuelle/permanente. */
class BlockedIp extends Model
{
    protected $table = 'blocked_ips';

    protected $fillable = ['ip', 'motif', 'tentatives', 'bloque_jusqua', 'permanent', 'bloque_par'];

    protected $casts = ['bloque_jusqua' => 'datetime', 'permanent' => 'boolean', 'tentatives' => 'integer'];

    public function bloquePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bloque_par');
    }

    public function estActif(): bool
    {
        return $this->permanent || ($this->bloque_jusqua !== null && $this->bloque_jusqua->isFuture());
    }

    public function scopeActif($q)
    {
        return $q->where(fn ($w) => $w->where('permanent', true)->orWhere('bloque_jusqua', '>', now()));
    }
}
