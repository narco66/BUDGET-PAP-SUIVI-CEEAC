<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrée d'historique / snapshot du cycle de vie budget. */
class BudgetHistorique extends Model
{
    protected $table = 'budget_historiques';

    protected $fillable = ['budget_id', 'version', 'action', 'snapshot', 'snapshot_complet', 'motif', 'user_id'];

    protected $casts = ['snapshot' => 'array', 'snapshot_complet' => 'boolean'];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
