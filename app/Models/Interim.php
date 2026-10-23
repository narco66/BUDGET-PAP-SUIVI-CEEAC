<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interim extends Model
{
    protected $fillable = [
        'structure_id', 'titulaire_user_id', 'interim_user_id',
        'date_debut', 'date_fin', 'motif', 'actif', 'cree_par',
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

    public function titulaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'titulaire_user_id');
    }

    public function interim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interim_user_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
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
