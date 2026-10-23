<?php

namespace App\Models\Concerns;

use App\Models\Indicateur;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Rattache des indicateurs polymorphiques à un élément de la chaîne RBM. */
trait HasIndicateurs
{
    public function indicateurs(): MorphMany
    {
        return $this->morphMany(Indicateur::class, 'rbm');
    }
}
