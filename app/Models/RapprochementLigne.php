<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapprochementLigne extends Model
{
    protected $table = 'rapprochement_lignes';

    protected $fillable = [
        'rapprochement_bancaire_id', 'date_operation', 'libelle', 'reference_bancaire',
        'montant', 'paiement_id', 'statut',
    ];

    protected $casts = [
        'date_operation' => 'date',
        'montant' => 'decimal:2',
    ];

    public function rapprochement(): BelongsTo
    {
        return $this->belongsTo(RapprochementBancaire::class, 'rapprochement_bancaire_id');
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}
