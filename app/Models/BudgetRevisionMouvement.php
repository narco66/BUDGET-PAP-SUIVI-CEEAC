<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



/** Mouvement de crédit rattaché à un acte de révision (phase B4). */

class BudgetRevisionMouvement extends Model

{

    protected $fillable = [

        'budget_revision_id', 'ligne_budgetaire_id', 'ligne_destination_id',

        'role', 'montant_avant', 'montant_apres', 'delta',

    ];



    protected $casts = [

        'montant_avant' => 'decimal:2',

        'montant_apres' => 'decimal:2',

        'delta' => 'decimal:2',

    ];



    public function revision(): BelongsTo

    {

        return $this->belongsTo(BudgetRevision::class, 'budget_revision_id');

    }



    public function ligneBudgetaire(): BelongsTo

    {

        return $this->belongsTo(LigneBudgetaire::class);

    }



    public function ligneDestination(): BelongsTo

    {

        return $this->belongsTo(LigneBudgetaire::class, 'ligne_destination_id');

    }

}

