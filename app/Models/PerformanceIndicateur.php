<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceIndicateur extends Model
{
    public $timestamps = false;

    protected $table = 'performances_indicateurs';

    protected $fillable = [
        'exercice_id', 'indicateur_id', 'periode',
        'valeur_cible', 'valeur_realisee', 'taux_atteinte', 'feu',
        'mesure_id', 'capture_par', 'capture_le',
    ];

    protected $casts = [
        'valeur_cible' => 'decimal:4',
        'valeur_realisee' => 'decimal:4',
        'taux_atteinte' => 'decimal:2',
        'capture_le' => 'datetime',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function indicateur(): BelongsTo
    {
        return $this->belongsTo(Indicateur::class);
    }

    public function mesure(): BelongsTo
    {
        return $this->belongsTo(Mesure::class);
    }

    public function captureur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capture_par');
    }
}
