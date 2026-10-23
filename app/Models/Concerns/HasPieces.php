<?php

namespace App\Models\Concerns;

use App\Models\PieceJustificative;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Rend un modèle porteur de pièces justificatives (GED). */
trait HasPieces
{
    public function pieces(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'attachable')->latest();
    }
}
