<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Détail à 5 chiffres du budget de fonctionnement, rattaché à la ligne budgétaire d'imputation. */
class LigneBudgetaireDetail extends Model
{
    use Auditable;

    protected $table = 'lignes_budgetaires_details';

    protected $fillable = ['exercice_id', 'ligne_budgetaire_id', 'code', 'intitule', 'montant_prevu', 'source'];

    protected $casts = ['montant_prevu' => 'decimal:2'];

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
