<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Anomalie détectée à l'analyse : ligne, colonne, valeur, règle, gravité, message et correction suggérée. */
class ImportAnomalie extends Model
{
    public const GRAVITES = [
        'bloquante' => 'Bloquante',
        'majeure' => 'Majeure',
        'avertissement' => 'Avertissement',
    ];

    protected $fillable = [
        'import_lot_id', 'import_ligne_id', 'numero_ligne', 'colonne', 'valeur', 'regle', 'gravite', 'message', 'suggestion',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ImportLot::class, 'import_lot_id');
    }

    public function ligne(): BelongsTo
    {
        return $this->belongsTo(ImportLigne::class, 'import_ligne_id');
    }
}
