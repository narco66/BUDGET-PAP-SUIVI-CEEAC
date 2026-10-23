<?php

namespace App\Models\Concerns;

use App\Models\Ged\GedDocument;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Documents GED rattachés polymorphiquement à l'entité métier. */
trait HasGedDocuments
{
    public function gedDocuments(): MorphMany
    {
        return $this->morphMany(GedDocument::class, 'attachable')->latest('date_depot');
    }
}
