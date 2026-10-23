<?php

namespace App\Models\Ged;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GedTag extends Model
{
    protected $table = 'ged_tags';

    protected $fillable = ['nom', 'couleur'];

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(GedDocument::class, 'ged_document_tags', 'tag_id', 'document_id')
            ->withTimestamps();
    }
}
