<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementDetail extends Model
{
    protected $table = 'paiement_details';

    protected $fillable = [
        'paiement_id', 'ligne_budgetaire_id', 'beneficiaire', 'reference', 'montant',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }
}
