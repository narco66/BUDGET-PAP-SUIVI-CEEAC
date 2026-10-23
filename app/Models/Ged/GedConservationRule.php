<?php

namespace App\Models\Ged;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GedConservationRule extends Model
{
    use SoftDeletes;

    protected $table = 'ged_conservation_rules';

    protected $fillable = [
        'type_document_id', 'categorie_id', 'duree_annees', 'sort_final',
        'alerte_jours_avant', 'verrouillage', 'description', 'actif', 'created_by',
    ];

    protected function casts(): array
    {
        return ['verrouillage' => 'boolean', 'actif' => 'boolean'];
    }

    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(GedTypeDocument::class, 'type_document_id');
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(GedCategorie::class, 'categorie_id');
    }
}
