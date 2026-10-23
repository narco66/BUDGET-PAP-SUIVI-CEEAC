<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueMesure extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'mesure_id', 'indicateur_id', 'periode', 'action',
        'valeur_avant', 'valeur_apres', 'statut_avant', 'statut_apres',
        'taux_avant', 'taux_apres', 'commentaire', 'user_id', 'created_at',
    ];

    protected $casts = [
        'valeur_avant' => 'decimal:4',
        'valeur_apres' => 'decimal:4',
        'taux_avant' => 'decimal:2',
        'taux_apres' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function mesure(): BelongsTo
    {
        return $this->belongsTo(Mesure::class);
    }

    public function indicateur(): BelongsTo
    {
        return $this->belongsTo(Indicateur::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
