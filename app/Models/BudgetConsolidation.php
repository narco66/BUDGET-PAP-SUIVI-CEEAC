<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



/** Snapshot persisté d'une consolidation budgétaire (phase B3). */

class BudgetConsolidation extends Model

{

    protected $fillable = [

        'budget_id', 'reference', 'version', 'snapshot', 'resultat',

        'lignes_count', 'montant_total', 'statut', 'user_id',

    ];



    protected $casts = [

        'snapshot' => 'array',

        'resultat' => 'array',

        'montant_total' => 'decimal:2',

    ];



    public function budget(): BelongsTo

    {

        return $this->belongsTo(Budget::class);

    }



    public function auteur(): BelongsTo

    {

        return $this->belongsTo(User::class, 'user_id');

    }

}

