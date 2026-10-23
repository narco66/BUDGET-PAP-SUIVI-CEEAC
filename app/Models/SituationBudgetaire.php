<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SituationBudgetaire extends Model
{
    public $timestamps = false;

    protected $table = 'situations_budgetaires';

    protected $fillable = [
        'exercice_id', 'date_situation',
        'budget_initial', 'budget_revise',
        'montant_engage', 'montant_liquide', 'montant_ordonnance', 'montant_paye',
        'credit_disponible', 'reste_a_engager', 'reste_a_payer',
        'taux_engagement', 'taux_liquidation', 'taux_ordonnancement', 'taux_paiement', 'taux_execution',
        'genere_par', 'created_at',
    ];

    protected $casts = [
        'date_situation' => 'date',
        'budget_initial' => 'decimal:2',
        'budget_revise' => 'decimal:2',
        'montant_engage' => 'decimal:2',
        'montant_liquide' => 'decimal:2',
        'montant_ordonnance' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'credit_disponible' => 'decimal:2',
        'reste_a_engager' => 'decimal:2',
        'reste_a_payer' => 'decimal:2',
        'taux_engagement' => 'decimal:2',
        'taux_liquidation' => 'decimal:2',
        'taux_ordonnancement' => 'decimal:2',
        'taux_paiement' => 'decimal:2',
        'taux_execution' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function generateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }
}
