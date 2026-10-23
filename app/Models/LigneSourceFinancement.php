<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneSourceFinancement extends Model
{
    protected $table = 'ligne_source_financement';

    protected $fillable = [
        'ligne_budgetaire_id', 'source_financement_id', 'montant', 'pourcentage', 'est_principale',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'pourcentage' => 'decimal:4',
        'est_principale' => 'boolean',
    ];

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }
}
