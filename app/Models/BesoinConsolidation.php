<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BesoinConsolidation extends Model
{
    protected $fillable = [
        'exercice_id', 'structure_id', 'reference', 'besoins_count', 'doublons_elimines',
        'montant_total', 'snapshot', 'statut', 'user_id',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'snapshot' => 'array',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
