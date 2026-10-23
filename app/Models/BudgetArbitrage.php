<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Décision d'arbitrage DPPB tracée (phase B2). */
class BudgetArbitrage extends Model
{
    protected $fillable = [
        'budget_id', 'budget_preparation_id', 'structure_id', 'user_id',
        'decision', 'montant_avant', 'montant_apres', 'motif',
    ];

    protected $casts = [
        'montant_avant' => 'decimal:2',
        'montant_apres' => 'decimal:2',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function preparation(): BelongsTo
    {
        return $this->belongsTo(BudgetPreparation::class, 'budget_preparation_id');
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
