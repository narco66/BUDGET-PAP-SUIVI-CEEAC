<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Modèle de correspondance réutilisable : colonne source → champ métier, par domaine. */
class ImportMapping extends Model
{
    protected $fillable = ['domaine', 'nom', 'colonnes', 'cree_par'];

    protected $casts = ['colonnes' => 'array'];

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
