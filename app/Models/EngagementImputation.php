<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementImputation extends Model
{
    protected $table = 'engagement_imputations';

    protected $fillable = ['engagement_id', 'ligne_budgetaire_id', 'montant'];

    protected $casts = ['montant' => 'decimal:2'];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }
}
