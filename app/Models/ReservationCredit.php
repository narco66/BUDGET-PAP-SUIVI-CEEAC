<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationCredit extends Model
{
    protected $table = 'reservations_credits';
    protected $fillable = [
        'engagement_id', 'ligne_budgetaire_id', 'montant', 'statut', 'liberee_le',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'liberee_le' => 'datetime',
    ];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }
}
