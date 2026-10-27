<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegation extends Model
{
    use Auditable;

    protected $fillable = [
        'structure_id', 'delegue_par', 'delegue_a',
        'date_debut', 'date_fin', 'motif', 'actif',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'actif' => 'boolean',
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function deleguant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegue_par');
    }

    public function delegue(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegue_a');
    }

    public function scopeEnCours($query)
    {
        $today = now()->toDateString();

        return $query->where('actif', true)
            ->whereDate('date_debut', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $today);
            });
    }
}
