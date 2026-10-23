<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Alerte de performance persistée (phase S4). */
class AlertePerformance extends Model
{
    protected $table = 'alertes_performance';

    protected $fillable = [
        'exercice_id', 'campagne_id', 'activite_id', 'produit_id',
        'type', 'gravite', 'reference', 'message', 'resolu', 'detecte_le',
    ];

    protected $casts = [
        'resolu' => 'boolean',
        'detecte_le' => 'datetime',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneSuivi::class, 'campagne_id');
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
