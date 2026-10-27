<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal immuable d'un lot : transitions de statut, décisions et faits techniques. */
class ImportEvenement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['import_lot_id', 'type', 'user_id', 'statut_avant', 'statut_apres', 'commentaire', 'donnees'];

    protected $casts = ['donnees' => 'array', 'created_at' => 'datetime'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ImportLot::class, 'import_lot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
