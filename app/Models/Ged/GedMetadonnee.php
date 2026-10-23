<?php

namespace App\Models\Ged;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedMetadonnee extends Model
{
    protected $table = 'ged_metadonnees';

    protected $fillable = ['document_id', 'cle', 'valeur', 'type_valeur'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }
}
